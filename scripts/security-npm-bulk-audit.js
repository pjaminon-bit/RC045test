#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const assert = require('node:assert/strict');
const zlib = require('node:zlib');

const BULK_AUDIT_URL = 'https://registry.npmjs.org/-/npm/v1/security/advisories/bulk';
const BLOKKERENDE_ERNS = new Set(['high', 'critical']);
const GELDIGE_ERNS = new Set(['info', 'low', 'moderate', 'high', 'critical']);

class AuditServiceError extends Error {}

function pakketnaamUitLockPad(pad, pakket) {
  if (pakket && typeof pakket.name === 'string' && pakket.name.trim() !== '') {
    return pakket.name.trim();
  }

  const marker = 'node_modules/';
  const index = pad.lastIndexOf(marker);
  if (index === -1) {
    return null;
  }

  const naam = pad.slice(index + marker.length).trim();
  return naam === '' ? null : naam;
}

function bouwBulkPayload(lock) {
  if (!lock || typeof lock !== 'object' || !lock.packages || typeof lock.packages !== 'object') {
    throw new Error('package-lock.json bevat geen geldige packages-sectie.');
  }

  const payload = Object.create(null);

  for (const [pad, pakket] of Object.entries(lock.packages)) {
    if (pad === '' || !pakket || typeof pakket !== 'object' || typeof pakket.version !== 'string') {
      continue;
    }

    const naam = pakketnaamUitLockPad(pad, pakket);
    const versie = pakket.version.trim();
    if (!naam || !versie) {
      continue;
    }

    if (!payload[naam]) {
      payload[naam] = [];
    }
    if (!payload[naam].includes(versie)) {
      payload[naam].push(versie);
    }
  }

  for (const versies of Object.values(payload)) {
    versies.sort();
  }

  return payload;
}

function decodeAuditBody(buffer) {
  let inhoud = Buffer.from(buffer);

  // npm registry heeft in 2026 tijdelijk gzip-body's zonder Content-Encoding
  // teruggegeven. Detecteer daarom de gzip magic bytes in plaats van alleen
  // op de responseheader te vertrouwen.
  if (inhoud.length >= 2 && inhoud[0] === 0x1f && inhoud[1] === 0x8b) {
    try {
      inhoud = zlib.gunzipSync(inhoud);
    } catch (fout) {
      throw new AuditServiceError(`gzip-respons kon niet worden uitgepakt: ${fout.message}`);
    }
  }

  let rapport;
  try {
    rapport = JSON.parse(inhoud.toString('utf8'));
  } catch (fout) {
    throw new AuditServiceError(`auditrespons is geen geldige JSON: ${fout.message}`);
  }

  if (!rapport || typeof rapport !== 'object' || Array.isArray(rapport)) {
    throw new AuditServiceError('auditrespons heeft geen geldig objectformaat.');
  }

  return rapport;
}

function verzamelBlokkerendeAdvisories(rapport) {
  const blokkerend = [];

  for (const [pakketnaam, advisories] of Object.entries(rapport)) {
    if (!Array.isArray(advisories)) {
      throw new AuditServiceError(`auditrespons voor ${pakketnaam} is geen advisorylijst.`);
    }

    for (const advisory of advisories) {
      if (!advisory || typeof advisory !== 'object') {
        throw new AuditServiceError(`auditrespons voor ${pakketnaam} bevat een ongeldig advisoryrecord.`);
      }

      const ernst = typeof advisory.severity === 'string'
        ? advisory.severity.toLowerCase()
        : '';
      if (!GELDIGE_ERNS.has(ernst)) {
        throw new AuditServiceError(`auditrespons voor ${pakketnaam} bevat onbekende severity '${ernst || 'leeg'}'.`);
      }

      if (BLOKKERENDE_ERNS.has(ernst)) {
        blokkerend.push({
          pakketnaam,
          ernst,
          id: advisory.id ?? 'onbekend',
          titel: advisory.title ?? 'zonder titel',
          url: advisory.url ?? '',
        });
      }
    }
  }

  return blokkerend;
}

function slaap(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function haalBulkAuditOp(payload, pogingen = 3, timeoutMs = 20000) {
  const body = JSON.stringify(payload);
  let laatsteFout = null;

  for (let poging = 1; poging <= pogingen; poging += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
      const response = await fetch(BULK_AUDIT_URL, {
        method: 'POST',
        headers: {
          'content-type': 'application/json',
          accept: 'application/json',
          'user-agent': 'rc045test-security-bulk-audit/1.0',
        },
        body,
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new AuditServiceError(`Bulk Advisory endpoint gaf HTTP ${response.status} ${response.statusText}`);
      }

      const buffer = Buffer.from(await response.arrayBuffer());
      return decodeAuditBody(buffer);
    } catch (fout) {
      laatsteFout = fout instanceof AuditServiceError
        ? fout
        : new AuditServiceError(fout.name === 'AbortError'
          ? `Bulk Advisory endpoint gaf binnen ${timeoutMs} ms geen antwoord.`
          : `Bulk Advisory request faalde: ${fout.message}`);

      if (poging < pogingen) {
        console.error(`WAARSCHUWING: dependency-audit poging ${poging}/${pogingen} faalde: ${laatsteFout.message}`);
        await slaap(1000 * poging);
      }
    } finally {
      clearTimeout(timer);
    }
  }

  throw laatsteFout ?? new AuditServiceError('Bulk Advisory audit is zonder resultaat beëindigd.');
}

function voerZelftestUit() {
  const voorbeeldLock = {
    packages: {
      '': { name: 'voorbeeld', version: '1.0.0' },
      'node_modules/foo': { version: '1.2.3' },
      'node_modules/@scope/bar': { version: '4.5.6' },
      'node_modules/foo/node_modules/baz': { version: '7.8.9' },
    },
  };

  assert.deepEqual(
    { ...bouwBulkPayload(voorbeeldLock) },
    {
      foo: ['1.2.3'],
      '@scope/bar': ['4.5.6'],
      baz: ['7.8.9'],
    }
  );

  const rapport = {
    foo: [{ id: 1, severity: 'high', title: 'test high', url: 'https://example.invalid/high' }],
    baz: [{ id: 2, severity: 'moderate', title: 'test moderate', url: 'https://example.invalid/moderate' }],
  };
  const gzip = zlib.gzipSync(Buffer.from(JSON.stringify(rapport)));
  assert.deepEqual(decodeAuditBody(gzip), rapport);
  assert.equal(verzamelBlokkerendeAdvisories(rapport).length, 1);
  assert.throws(
    () => verzamelBlokkerendeAdvisories({ foo: [{ severity: 'mystery' }] }),
    AuditServiceError
  );

  console.log('Bulk dependency audit zelftest: OK');
}

async function main() {
  const argument = process.argv[2] ?? 'package-lock.json';
  if (argument === '--self-test') {
    voerZelftestUit();
    return;
  }

  let lock;
  try {
    lock = JSON.parse(fs.readFileSync(argument, 'utf8'));
  } catch (fout) {
    console.error(`FOUT: lockfile '${argument}' kon niet veilig worden gelezen: ${fout.message}`);
    process.exitCode = 2;
    return;
  }

  let payload;
  try {
    payload = bouwBulkPayload(lock);
  } catch (fout) {
    console.error(`FOUT: dependency-audit payload kon niet worden opgebouwd: ${fout.message}`);
    process.exitCode = 2;
    return;
  }

  const aantalPakketten = Object.keys(payload).length;
  if (aantalPakketten === 0) {
    console.log('Bulk dependency audit: geen gelockte dependencies gevonden.');
    return;
  }

  try {
    const rapport = await haalBulkAuditOp(payload);
    const blokkerend = verzamelBlokkerendeAdvisories(rapport);

    if (blokkerend.length > 0) {
      console.error(`FOUT: ${blokkerend.length} HIGH/CRITICAL dependency-kwetsbaarheid/kwetsbaarheden gevonden:`);
      for (const advisory of blokkerend) {
        const verwijzing = advisory.url ? ` ${advisory.url}` : '';
        console.error(`- [${advisory.ernst.toUpperCase()}] ${advisory.pakketnaam}: ${advisory.titel} (id ${advisory.id})${verwijzing}`);
      }
      process.exitCode = 1;
      return;
    }

    console.log(`Bulk dependency audit: geen HIGH/CRITICAL kwetsbaarheden gevonden in ${aantalPakketten} gelockte pakketten.`);
  } catch (fout) {
    const melding = fout instanceof AuditServiceError ? fout.message : String(fout);
    console.error(`AUDIT-SERVICEFOUT: ${melding}`);
    console.error('De securitygate blijft gesloten; dit is geen bewijs dat dependencies veilig zijn.');
    process.exitCode = 2;
  }
}

main().catch((fout) => {
  console.error(`FOUT: onverwachte dependency-auditfout: ${fout.stack || fout}`);
  process.exitCode = 2;
});

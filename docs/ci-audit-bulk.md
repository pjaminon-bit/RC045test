# Dependency-auditcontract

De verplichte `Security supply-chain`-workflow gebruikt rechtstreeks het door npm gedocumenteerde Bulk Advisory-endpoint. De helper `scripts/security-npm-bulk-audit.js` bouwt de auditpayload uit `package-lock.json`, verwerkt ook foutief ongelabelde gzip-responses en blokkeert op HIGH/CRITICAL advisories.

Een netwerk-, HTTP-, parse- of onbekende-severityfout geldt nadrukkelijk niet als een schone audit: de helper eindigt dan met exitcode 2 en de securitygate blijft gesloten. De eigen source-policy- en pre-VPS-securitychecks draaien met `always()` zodat zij niet meer door een ongerelateerde registryfout worden overgeslagen.

<?php
// ============================================================
// Same-origin contactinbox runtime
// ============================================================
// De gedeelde template bevat historisch een externe Formspree-action. Deze
// laatste gerichte outputguard vervangt uitsluitend het publieke contactform
// door de eigen endpoint en maakt een eerder tenant-disabled formulier actief.
// ============================================================

function contactInboxRuntimeTransform(string $html): string
{
    if($html===''||stripos($html,'id="contact-form"')===false&&stripos($html,"id='contact-form'")===false)return $html;

    $html=preg_replace_callback(
        '~<form\b[^>]*\bid=["\']contact-form["\'][^>]*>.*?</form>~is',
        static function(array $m): string{
            $form=$m[0];
            if(preg_match('~\baction=["\'][^"\']*["\']~i',$form)===1)$form=preg_replace('~\baction=["\'][^"\']*["\']~i','action="contact-ontvangst.php"',$form,1)??$form;
            else $form=preg_replace('~<form\b~i','<form action="contact-ontvangst.php"',$form,1)??$form;
            $form=preg_replace('~\sdata-tenant-disabled=["\']1["\']~i','',$form)??$form;
            $form=preg_replace_callback(
                '~<button\b[^>]*\btype=["\']submit["\'][^>]*>~i',
                static function(array $b): string{return preg_replace('~\sdisabled(?:=["\']disabled["\'])?~i','',$b[0])??$b[0];},
                $form,
                1
            )??$form;
            return $form;
        },
        $html,
        1
    )??$html;

    // Defense-in-depth: het contactformulier mag na transformatie nooit nog
    // een externe Formspree-action of tenant-disabled vlag bevatten.
    if(preg_match('~<form\b[^>]*\bid=["\']contact-form["\'][^>]*\baction=["\']https?://~i',$html)===1||preg_match('~<form\b[^>]*\bid=["\']contact-form["\'][^>]*data-tenant-disabled~i',$html)===1){
        throw new RuntimeException('Contactformulier kon niet veilig naar de tenantinbox worden gerouteerd.');
    }
    return $html;
}

function contactInboxRuntimeStart(): void
{
    if(PHP_SAPI==='cli')return;
    static $gestart=false;if($gestart)return;$gestart=true;
    ob_start(static fn(string $html): string=>contactInboxRuntimeTransform($html));
}

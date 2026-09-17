<?php


declare(strict_types=1);


namespace App\Core;


final class Validator
{
    public static function int(mixed $value, int $default = 0): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
    }


    public static function bool(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
    }


    public static function requiredString(mixed $value, string $label, int $max, array &$errors): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            $errors[] = "$label è obbligatorio.";
        } elseif (mb_strlen($value) > $max) {
            $errors[] = "$label supera $max caratteri.";
        }
        return $value;
    }


    public static function optionalString(mixed $value, int $max, string $label, array &$errors): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (mb_strlen($value) > $max) $errors[] = "$label supera $max caratteri.";
        return $value;
    }


    public static function naturalText(mixed $value): string
    {
        $value = preg_replace('/\\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);
        if ($value === '') return '';
        $upper = mb_strtoupper($value, 'UTF-8');
        $lower = mb_strtolower($value, 'UTF-8');
        if ($value === $upper || $value === $lower) {
            return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
        }
        return $value;
    }


    public static function provinceCode(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/\\(([A-Z]{2})\\)\\s*$/i', $value, $match)) return strtoupper($match[1]);
        $plain = mb_strtoupper($value, 'UTF-8');
        if (preg_match('/^[A-Z]{2}$/', $plain)) return $plain;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $plain);
        $key = preg_replace('/[^A-Z]/', '', $ascii !== false ? $ascii : $plain) ?? '';
        $map = [
            'AGRIGENTO'=>'AG','ALESSANDRIA'=>'AL','ANCONA'=>'AN','AOSTA'=>'AO','AREZZO'=>'AR','ASCOLIPICENO'=>'AP','ASTI'=>'AT','AVELLINO'=>'AV',
            'BARI'=>'BA','BARLETTAANDRIATRANI'=>'BT','BELLUNO'=>'BL','BENEVENTO'=>'BN','BERGAMO'=>'BG','BIELLA'=>'BI','BOLOGNA'=>'BO','BOLZANO'=>'BZ','BRESCIA'=>'BS','BRINDISI'=>'BR',
            'CAGLIARI'=>'CA','CALTANISSETTA'=>'CL','CAMPOBASSO'=>'CB','CASERTA'=>'CE','CATANIA'=>'CT','CATANZARO'=>'CZ','CHIETI'=>'CH','COMO'=>'CO','COSENZA'=>'CS','CREMONA'=>'CR','CROTONE'=>'KR','CUNEO'=>'CN',
            'ENNA'=>'EN','FERMO'=>'FM','FERRARA'=>'FE','FIRENZE'=>'FI','FOGGIA'=>'FG','FORLICESENA'=>'FC','FROSINONE'=>'FR',
            'GENOVA'=>'GE','GORIZIA'=>'GO','GROSSETO'=>'GR','IMPERIA'=>'IM','ISERNIA'=>'IS','LAQUILA'=>'AQ','LASPEZIA'=>'SP','LATINA'=>'LT','LECCE'=>'LE','LECCO'=>'LC','LIVORNO'=>'LI','LODI'=>'LO','LUCCA'=>'LU',
            'MACERATA'=>'MC','MANTOVA'=>'MN','MASSACARRARA'=>'MS','MATERA'=>'MT','MESSINA'=>'ME','MILANO'=>'MI','MODENA'=>'MO','MONZAEBRIANZA'=>'MB',
            'NAPOLI'=>'NA','NOVARA'=>'NO','NUORO'=>'NU','ORISTANO'=>'OR','PADOVA'=>'PD','PALERMO'=>'PA','PARMA'=>'PR','PAVIA'=>'PV','PERUGIA'=>'PG','PESAROURBINO'=>'PU','PESCARA'=>'PE','PIACENZA'=>'PC','PISA'=>'PI','PISTOIA'=>'PT','PORDENONE'=>'PN','POTENZA'=>'PZ','PRATO'=>'PO',
            'RAGUSA'=>'RG','RAVENNA'=>'RA','REGGIOCALABRIA'=>'RC','REGGIOEMILIA'=>'RE','RIETI'=>'RI','RIMINI'=>'RN','ROMA'=>'RM','ROVIGO'=>'RO',
            'SALERNO'=>'SA','SASSARI'=>'SS','SAVONA'=>'SV','SIENA'=>'SI','SIRACUSA'=>'SR','SONDRIO'=>'SO','SUDSARDEGNA'=>'SU',
            'TARANTO'=>'TA','TERAMO'=>'TE','TERNI'=>'TR','TORINO'=>'TO','TRAPANI'=>'TP','TRENTO'=>'TN','TREVISO'=>'TV','TRIESTE'=>'TS',
            'UDINE'=>'UD','VARESE'=>'VA','VENEZIA'=>'VE','VERBANOCUSIOOSSOLA'=>'VB','VERCELLI'=>'VC','VERONA'=>'VR','VIBOVALENTIA'=>'VV','VICENZA'=>'VI','VITERBO'=>'VT'
        ];
        return $map[$key] ?? $plain;
    }



    public static function fiscalCode(mixed $value): bool
    {
        $code = strtoupper(preg_replace('/\s+/', '', trim((string) $value)) ?? '');
        if (!preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $code)) {
            return false;
        }

        $odd = [
            '0'=>1,'1'=>0,'2'=>5,'3'=>7,'4'=>9,'5'=>13,'6'=>15,'7'=>17,'8'=>19,'9'=>21,
            'A'=>1,'B'=>0,'C'=>5,'D'=>7,'E'=>9,'F'=>13,'G'=>15,'H'=>17,'I'=>19,'J'=>21,
            'K'=>2,'L'=>4,'M'=>18,'N'=>20,'O'=>11,'P'=>3,'Q'=>6,'R'=>8,'S'=>12,'T'=>14,
            'U'=>16,'V'=>10,'W'=>22,'X'=>25,'Y'=>24,'Z'=>23
        ];
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $char = $code[$i];
            $sum += $i % 2 === 0 ? $odd[$char] : (ctype_digit($char) ? (int) $char : ord($char) - 65);
        }
        return $code[15] === chr(($sum % 26) + 65);
    }

    public static function vatNumber(mixed $value): bool
    {
        $vat = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
        if (!preg_match('/^\d{11}$/', $vat) || $vat === '00000000000') return false;

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = (int) $vat[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) $digit -= 9;
            }
            $sum += $digit;
        }
        return (10 - ($sum % 10)) % 10 === (int) $vat[10];
    }

    public static function fiscalCodeOrVat(mixed $value): bool
    {
        return self::fiscalCode($value) || self::vatNumber($value);
    }

    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}

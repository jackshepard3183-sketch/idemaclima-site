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

    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}

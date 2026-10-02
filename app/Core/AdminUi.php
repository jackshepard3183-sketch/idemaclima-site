<?php

declare(strict_types=1);

namespace App\Core;

/** Render shared admin actions before sending HTML to the browser. */
final class AdminUi
{
    private static bool $installed = false;

    public static function install(): void
    {
        if (self::$installed) return;
        self::$installed = true;
        ob_start([self::class, 'render']);
    }

    public static function icon(string $name): string
    {
        $paths = [
            'open'=>'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
            'edit'=>'<path d="m4 16 12-12 4 4L8 20H4v-4Z"/><path d="m14 6 4 4"/>',
            'duplicate'=>'<rect x="9" y="9" width="11" height="12" rx="1"/><path d="M6 15H3V3h11v3"/>',
            'delete'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
            'new'=>'<path d="M12 5v14M5 12h14"/>',
            'save'=>'<path d="M4 3h13l4 4v14H3V3h1Z"/><path d="M7 3v6h10V3M7 21v-8h10v8"/>',
            'download'=>'<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
            'upload'=>'<path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/>',
            'back'=>'<path d="m10 5-7 7 7 7M3 12h18"/>',
            'filter'=>'<path d="M3 3h18l-7 8v8l-4 2V11Z"/>',
            'search'=>'<circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/>',
            'more'=>'<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
            'cancel'=>'<path d="m6 6 12 12M6 18 18 6"/>',
            'product'=>'<path d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"/>',
            'document'=>'<path d="M6 3h8l4 4v14H6zM14 3v5h4M9 12h6M9 16h6"/>',
            'reference'=>'<path d="M4 21h16V8l-5-5H4zM14 3v6h6M8 14h8M8 18h5"/>',
            'catalog'=>'<path d="M4 5a3 3 0 0 1 3-3h13v17H7a3 3 0 0 0-3 3zM4 5v17M8 6h8M8 10h8"/>',
        ];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.($paths[$name] ?? $paths['document']).'</svg>';
    }

    public static function render(string $html): string
    {
        // Large contact archives exceed PCRE's backtracking limit with a whole-main regex.
        $start = stripos($html, '<main');
        if ($start === false) return $html;
        $bodyStart = strpos($html, '>', $start);
        $end = strripos($html, '</main>');
        if ($bodyStart === false || $end === false || $end <= $bodyStart) return $html;
        $bodyStart++;
        $main = ['', '', substr($html, $bodyStart, $end - $bodyStart)];
            $body = preg_replace_callback(
                '#<(script|style)\b[^>]*>.*?</\1\s*>|<(a|button)\b([^>]*)>(.*?)</\2\s*>#is',
                static function (array $m): string {
                    if (!empty($m[1])) return $m[0]; // Never rewrite script/style contents.
                    $attrs=$m[3]; $content=$m[4];
                    if (preg_match('#<h[1-6]\b|<p\b#i',$content)) return $m[0];
                    if (preg_match('/\b(?:v2-action|global-sort-button|sort-button|pw-step|pw-flow-step)\b/',$attrs)) return $m[0];
                    $label=html_entity_decode(strip_tags(preg_replace('#<svg\b[^>]*>.*?</svg>#is','',$content) ?? $content),ENT_QUOTES,'UTF-8');
                    $label=preg_replace('/^[\s↗✎⌫⧉✓↓↑＋+←◉➜≡⌕]+/u','',trim($label)) ?? $label;
                    $rules=[
                        'delete'=>'elimina|rimuovi|cancella', 'save'=>'salva|conferma|applica',
                        'new'=>'aggiungi|nuov[oa]|crea|inserisci', 'edit'=>'modifica|gestisci|editor',
                        'duplicate'=>'duplica|copia','download'=>'esporta|scarica|download',
                        'upload'=>'importa|carica|upload','back'=>'torna|indietro','cancel'=>'annulla',
                        'filter'=>'filtra|filtro','search'=>'cerca|trova','open'=>'apri|visualizza|dettagli|vai a','more'=>'altro',
                    ];
                    $kind=null;
                    foreach($rules as $name=>$words) if(preg_match('/^(?:'.$words.')\b/iu',$label)){$kind=$name;break;}
                    if($kind===null) return $m[0];
                    // Old templates can already contain SVGs or a glyph inside an icon wrapper.
                    $content=preg_replace('#<(span|i)\b[^>]*class=["\'][^"\']*(?:admin-btn-icon|action-icon)[^"\']*["\'][^>]*>.*?</\1>#is','',$content) ?? $content;
                    $content=preg_replace('#<svg\b[^>]*>.*?</svg>#is','',$content) ?? $content;
                    $content=preg_replace('#<(i|span)\b[^>]*>\s*[↗✎⌫⧉✓↓↑＋+←◉➜≡⌕]+\s*</\1>#u','',$content) ?? $content;
                    $content=preg_replace('/^[\s↗✎⌫⧉✓↓↑＋+←◉➜≡⌕]+/u','',$content) ?? $content;
                    if(preg_match('/\bclass=(["\'])(.*?)\1/is',$attrs)){
                        $attrs=preg_replace_callback('/\bclass=(["\'])(.*?)\1/is',static fn(array $c):string=>'class='.$c[1].trim($c[2]).(str_contains(' '.$c[2].' ',' idema-action ')?'':' idema-action').$c[1],$attrs) ?? $attrs;
                    }else{$attrs.=' class="idema-action"';}
                    $attrs=preg_replace('/\s+data-(?:action-style|ui-icon)=("[^"]*"|\'[^\']*\')/i','',$attrs) ?? $attrs;
                    $attrs.=' data-action-style="'.$kind.'" data-ui-icon="1"';
                    return '<'.$m[2].$attrs.'><span class="admin-btn-icon" aria-hidden="true">'.self::icon($kind).'</span>'.$content.'</'.$m[2].'>';
                },$main[2]) ?? $main[2];
            $body=preg_replace_callback('#<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<span\b([^>]*)>([^<]*)</span>#is',static function(array $m):string{
                if(!isset($m[1]))return $m[0];
                if(!preg_match('/\bclass=["\'][^"\']*\bbadge\b/',$m[1]))return $m[0];
                $label=mb_strtolower(trim(html_entity_decode($m[2],ENT_QUOTES,'UTF-8')));
                $states=['active'=>['attivo','attiva','pubblicato','pubblicata','approvato','approvata','completato','completata'],'inactive'=>['inattivo','inattiva','disattivo','disattiva','disabilitato','disabilitata'],'draft'=>['bozza','nuova','nuovo','new'],'review'=>['in revisione','da approvare','in attesa','in lavorazione','pending','in_progress'],'error'=>['errore','rifiutato','rifiutata']];
                foreach($states as $state=>$labels)if(in_array($label,$labels,true)){
                    $attrs=preg_replace('/\s+data-status-style=("[^"]*"|\'[^\']*\')/i','',$m[1]) ?? $m[1];
                    return '<span'.$attrs.' data-status-style="'.$state.'">'.$m[2].'</span>';
                }
                return $m[0];
            },$body) ?? $body;
        return substr($html, 0, $bodyStart).$body.substr($html, $end);
    }
}

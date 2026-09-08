<?php
$funcs = array('__'=>1,'_e'=>1,'esc_html__'=>1,'esc_html_e'=>1,'esc_attr__'=>1,'esc_attr_e'=>1,'_x'=>2,'_n'=>3,'_nx'=>4,'_n_noop'=>3,'_nx_noop'=>4);
$strings = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( dirname( __DIR__ ) ));
foreach ($rii as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php') continue;
    // `_to_delete/` holds copies of files this environment could not remove.
    // Scanning it counts every string twice and files them under a path that
    // does not exist in the repository.
    if (str_contains($path, '/vendor/') || str_contains($path, '/tools/') || str_contains($path,'/tests/') || str_contains($path,'/node_modules/') || str_contains($path,'/_to_delete/')) continue;
    $tokens = token_get_all(file_get_contents($path));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING || !isset($funcs[$t[1]])) continue;
        // next non-whitespace must be (
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if ($tokens[$j] !== '(') continue;
        $args = array(); $depth = 0; $current = null;
        for ($k = $j; $k < $count; $k++) {
            $tok = $tokens[$k];
            if ($tok === '(') { $depth++; continue; }
            if ($tok === ')') { $depth--; if ($depth === 0) break; continue; }
            if ($tok === ',' && $depth === 1) { $args[] = $current; $current = null; continue; }
            if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING && $depth === 1 && $current === null) {
                $current = stripcslashes(substr($tok[1], 1, -1));
                if ($tok[1][0] === "'") $current = str_replace(array("\\'","\\\\"), array("'","\\"), substr($tok[1],1,-1));
            }
        }
        $args[] = $current;
        $fn = $t[1];
        $singular = $args[0] ?? null;
        $plural = null;
        // A context is part of what a string is: `_x( 'Advanced', … )` said of
        // a group of girls and of a mixed one are two different translations
        // of one English word, and without the context they collapse into one.
        $context = null;
        if ($fn === '_x') { $context = $args[1] ?? null; }
        // `_n_noop()` registers a pair for gettext without translating it there
        // and then; the catalogue needs it all the same, or the count beside a
        // status in the admin list is the only English left on the screen.
        if ($fn === '_n' || $fn === '_n_noop') { $plural = $args[1] ?? null; }
        if ($fn === '_nx' || $fn === '_nx_noop') { $plural = $args[1] ?? null; $context = $args[3] ?? null; }
        if ($singular === null) continue;
        $key = $singular . "\x00" . (string) $plural . "\x00" . (string) $context;
        if (!isset($strings[$key])) $strings[$key] = array('singular'=>$singular,'plural'=>$plural,'context'=>$context,'refs'=>array());
        $strings[$key]['refs'][] = ltrim( str_replace( dirname( __DIR__ ), '', $path ), '/' ) . ':' . $t[2];
    }
}
ksort($strings);

// The order a directory hands back its files is the filesystem's business, not
// the plugin's, and this file is committed: without sorting, running the
// extractor on two machines produces two different files that say the same
// thing.
foreach ($strings as $key => $string) { sort($strings[$key]['refs']); }

file_put_contents( __DIR__ . '/i18n/strings.json', json_encode(array_values($strings), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo count($strings), " strings\n";

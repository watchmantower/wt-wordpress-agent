<?php
/**
 * The plugin calls unqualified time() inside its own namespaces; PHP resolves
 * those to a namespaced function first, so these let tests pin "now" without
 * touching global state. $GLOBALS['wthb_test_now'] = null means real time.
 */

namespace WTHB\includes\rest {
    function time(): int
    {
        return $GLOBALS['wthb_test_now'] ?? \time();
    }
}

namespace WTHB\core {
    function time(): int
    {
        return $GLOBALS['wthb_test_now'] ?? \time();
    }
}

namespace WTHB\models {
    function time(): int
    {
        return $GLOBALS['wthb_test_now'] ?? \time();
    }
}

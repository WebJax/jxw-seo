<?php
/**
 * Deactivator Class
 *
 * Handles plugin deactivation tasks
 */

namespace LocalSEO;

class Deactivator {
    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}

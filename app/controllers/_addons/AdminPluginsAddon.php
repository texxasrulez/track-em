<?php
namespace TrackEm\Controllers\_addons;

trait AdminPluginsAddon {
    public function admin_plugins(): void {
        require __DIR__ . '/../../views/admin/plugins.php';
        exit;
    }
}

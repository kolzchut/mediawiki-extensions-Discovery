<?php 

class DiscoveryHooks
{

    public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin) {
        $out->addModules('ext.discovery.scripts');

        return true;
    }

}

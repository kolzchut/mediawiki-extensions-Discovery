<?php

class DiscoveryHooks {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'discovery', 'DiscoveryHooks::renderTagDiscovery' );
	}

	/**
	 * @param $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string
	 */
	public static function renderTagDiscovery( $input, array $args, Parser $parser ) {
		$parser->getOutput()->addModules( 'ext.discovery' );
		return self::getDiscoveryHTML();
	}


	static function getDiscoveryHTML() {
		$data['title'] = wfMessage( 'discovery-component-title' )->text();
		$templateParser = new \TemplateParser( __DIR__ . '/templates' );
		$html = $templateParser->processTemplate( 'discoveryComponent', $data );

		return $html;
	}

	/**
	 * Hook: ResourceLoaderGetConfigVars called right before
	 * ResourceLoaderStartUpModule::getConfig returns
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param &$vars array of variables to be added into the output of the startup module.
	 *
	 * @return true
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgDiscoveryConfig;
		$vars['wgDiscoveryConfig'] = $wgDiscoveryConfig;

		return true;
	}

}

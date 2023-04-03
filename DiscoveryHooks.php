<?php

class DiscoveryHooks {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'discovery', [ self::class, 'renderTagDiscovery' ] );
		$parser->setFunctionHook( 'disable_discovery', [ self::class, 'parserFunctionDisableDiscovery' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 *
	 * @return string|array
	 */
	public static function renderTagDiscovery( string $input, array $args, Parser $parser ) {
		if ( !$parser->getOutput()->getProperty( 'discovery-disabled' ) ) {
			$parser->getOutput()->addModules( 'ext.discovery' );
			return self::getDiscoveryHTML();
		}

		return [ '', 'markerType' => 'nowiki' ];
	}

	/**
	 * @param Parser $parser
	 * @param string $text
	 */
	public static function parserFunctionDisableDiscovery( Parser $parser, string $text ) {
		$parser->getOutput()->setProperty( 'discovery-disabled', true );
		$parser->getOutput()->addJsConfigVars( 'discovery-disabled', true );
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

	/**
	 * Save data from ParserOutput to OutputPage

	 * OutputPageParserOutput hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 *
	 * @param OutputPage &$out
	 * @param ParserOutput $parserOutput
	 */
	static public function onOutputPageParserOutput( &$out, $parserOutput ) : void {
		$out->setProperty( 'discovery-disabled', $parserOutput->getProperty( 'discovery-disabled' ) );
	}

}

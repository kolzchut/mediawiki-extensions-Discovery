<?php
/**
 * @license MIT
 * @file
 */

namespace MediaWiki\Extension\Discovery;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use Parser;

class Hooks implements
	ParserFirstCallInitHook,
	ResourceLoaderGetConfigVarsHook,
	OutputPageParserOutputHook
{

	/**
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'discovery', [ $this, 'renderTagDiscovery' ] );
		$parser->setFunctionHook( 'disable_discovery', [ $this, 'parserFunctionDisableDiscovery' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 *
	 * @return string|array
	 */
	public function renderTagDiscovery( string $input, array $args, Parser $parser ) {
		if ( !$parser->getOutput()->getPageProperty( 'discovery-disabled' ) ) {
			$parser->getOutput()->addModules( [ 'ext.discovery' ] );
			return $this->getDiscoveryHTML();
		}

		return [ '', 'markerType' => 'nowiki' ];
	}

	/**
	 * @param Parser $parser
	 * @param string $text
	 */
	public function parserFunctionDisableDiscovery( Parser $parser, string $text ) {
		$parser->getOutput()->setPageProperty( 'discovery-disabled', true );
		$parser->getOutput()->addJsConfigVars( 'discovery-disabled', true );
	}

	/**
	 * @return string
	 */
	private function getDiscoveryHTML() {
		$data['title'] = wfMessage( 'discovery-component-title' )->text();
		$templateParser = new \TemplateParser( __DIR__ . '/../templates' );
		$html = $templateParser->processTemplate( 'discoveryComponent', $data );

		return $html;
	}

	/**
	 * @inheritDoc
	 */
	public function onResourceLoaderGetConfigVars( &$vars, $skin, $config ): void {
		$vars['wgDiscoveryConfig'] = $config->get( 'DiscoveryConfig' );
	}

	/**
	 * @inheritDoc
	 */
	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		$out->setProperty( 'discovery-disabled', $parserOutput->getPageProperty( 'discovery-disabled' ) );
	}

}

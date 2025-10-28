<?php
namespace inventor96\MakoTemplatePP;

use mako\application\Package;
use mako\view\ViewFactory;

class TemplatePackage extends Package {
	protected string $packageName = 'inventor96/mako-template-pp';
	protected string $fileNamespace = 'templatepp';

	/**
	 * @inheritDoc
	 */
	function bootstrap(): void {
		// register the template class as the .tpl.php renderer
		$this->container->get(ViewFactory::class)->extend('.tpl.php', TemplatePPRenderer::class);

		// register the filter registry as a singleton
		if (!$this->container->has(FilterRegistry::class)) {
			$this->container->registerSingleton([FilterRegistry::class, 'filterRegistry'], FilterRegistry::class);
		}
	}
}
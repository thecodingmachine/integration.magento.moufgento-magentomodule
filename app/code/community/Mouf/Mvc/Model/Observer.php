<?php
use \Symfony\Component\HttpFoundation\Request;
use \Mouf\Integration\Magento\MagentoFallbackResponse;
use \Mouf\Integration\Magento\MagentoHtmlElementBlock;

class Mouf_Mvc_Model_Observer extends Varien_Event_Observer
{
	public function onDispatchToMouf($observer) {
		// Let's see if we can dispatch anything in Mouf!
		require_once __DIR__.'/../../../../../../mouf/Mouf.php';

		$defaultMagentoController = $observer->getData('controller_action');

		/* @var $defaultMagentoController Mage_Cms_IndexController */

		$request = Request::createFromGlobals();

		$defaultRouter = Mouf::getDefaultRouter();

		$magentoTemplate = Mouf::getMagentoTemplate();
		$magentoTemplate->setLayout($defaultMagentoController->getLayout());

		define('ROOT_URL', parse_url(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_PATH));

		$response = $defaultRouter->handle($request);

		if (!$response instanceof MagentoFallbackResponse) {

			if ($magentoTemplate->isToHtmlTriggered()) {
				// TODO: get content of $response and put it inside magento response.

				$defaultMagentoController->loadLayout();

				$contentBlock = Mouf::getMagentoTemplate()->getContent();
				$magentoContentBlock = new MagentoHtmlElementBlock($contentBlock);

				$defaultMagentoController->getLayout()->addBlock($magentoContentBlock, "my.content.mouf.block");

				foreach ($magentoTemplate->getBlocks() as $blockName => $moufBlock) {
					$magentoBlock = new MagentoHtmlElementBlock($moufBlock);
					$defaultMagentoController->getLayout()->addBlock($magentoBlock, "mouf.".$blockName);
					$defaultMagentoController->getLayout()->getBlock($blockName)->append($magentoBlock);
				}

				$defaultMagentoController->getLayout()->getBlock('root')->setTemplate($magentoTemplate->getTemplate());

				$defaultMagentoController->getLayout()->getBlock('content')->append($magentoContentBlock /*$defaultMagentoController->getLayout()->createBlock('page/html_cookieNotice')*/);

				$defaultMagentoController->renderLayout();

				$defaultMagentoController->getResponse()->sendResponse();
			} else {
				$response->send();
			}
			// Exit not very good, but we are acting on an event that does not allow us to do otherwise.
			exit;
		}
	}

}
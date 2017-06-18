<?php
/**
 * @file
 * Contains \Drupal\example\Controller\ExampleController.
 */
namespace Drupal\baw_breadcrumb\Controller;
use Drupal\Core\Controller\ControllerBase;

class BawBreadcrumbStartController {
	public function startAction() {
		return [
			'#markup' => '<h2>Welcome to the start page</h2>',
		];
	}
}

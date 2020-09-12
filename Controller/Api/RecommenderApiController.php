<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecommenderBundle\Controller\Api;

use FOS\RestBundle\Util\Codes;
use Mautic\ApiBundle\Controller\CommonApiController;
use MauticPlugin\MauticRecommenderBundle\Api\Service\ApiCommands;
use MauticPlugin\MauticRecommenderBundle\Events\Processor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class RecommenderApiController.
 */
class RecommenderApiController extends CommonApiController
{
    /**
     * @var Processor
     */
    private $processor;
    /**
     * @var array
     */
    private $allowedEvents = ['RecommenderEvent'];

    /**
     * @param FilterControllerEvent $event
     */
    public function initialize(FilterControllerEvent $event)
    {
        $this->processor = $this->get('mautic.recommender.events.processor');
    }


    /**
     * @param $component
     *
     * @return array|Response
     */
    public function processAction($component)
    {
        defined('IN_MAUTIC_API') or define('IN_MAUTIC_API', 1);
        if (!in_array($component, $this->allowedEvents)) {
            return $this->badRequest(
                sprintf("%s is not allowed. You can use just %s", $component, implode(', ', $this->allowedEvents))
            );
        } elseif (empty($this->request->request->all())) {
            $view = $this->view(['error' => 'Parameters cannot be empty.'], Codes::HTTP_OK);

            return $this->handleView($view);
        }
        $eventDetail = $this->request->request->all();
        try {
            $this->processor->process($eventDetail);
            /** @var ApiCommands $apiCommands */
            $view  = $this->view(['success'=>'1'], Codes::HTTP_OK);
            return $this->handleView($view);
        } catch (\Exception $exception) {
            return $this->badRequest($exception->getMessage());
        }
    }
}

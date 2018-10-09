<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecommenderBundle\Model;

use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Model\AjaxLookupModelInterface;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Model\TranslationModelTrait;
use Mautic\CoreBundle\Model\VariantModelTrait;
use MauticPlugin\MauticMTCPilotBundle\MTCPilotEvents;
use MauticPlugin\MauticMTCPilotBundle\Entity\MTCPilot;
use MauticPlugin\MauticMTCPilotBundle\Entity\MTCPilotRepository;
use MauticPlugin\MauticMTCPilotBundle\Entity\Stat;
use MauticPlugin\MauticMTCPilotBundle\Event\MTCPilotEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticRecommenderBundle\Entity\Recommender;
use MauticPlugin\MauticRecommenderBundle\Entity\RecommenderRepository;
use MauticPlugin\MauticRecommenderBundle\Event\RecommenderEvent;
use MauticPlugin\MauticRecommenderBundle\RecommenderEvents;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RecommenderModel extends FormModel implements AjaxLookupModelInterface
{

    /**
     * Retrieve the permissions base.
     *
     * @return string
     */
    public function getPermissionBase()
    {
        return 'recommender:recommender';
    }

    /**
     * {@inheritdoc}
     *
     * @return RecommenderRepository
     */
    public function getRepository()
    {
        /** @var RecommenderRepository $repo */
        $repo = $this->em->getRepository('MauticRecommenderBundle:Recommender');

        $repo->setTranslator($this->translator);

        return $repo;
    }

    /**
     * Here just so PHPStorm calms down about type hinting.
     *
     * @param null $id
     *
     * @return null|Recommender
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            return new Recommender();
        }

        return parent::getEntity($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param       $entity
     * @param       $formFactory
     * @param null  $action
     * @param array $options
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!$entity instanceof Recommender) {
            throw new \InvalidArgumentException('Entity must be of class Recommender');
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create('recommender', $entity, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param $action
     * @param $entity
     * @param $isNew
     * @param $event
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof Recommender) {
            throw new MethodNotAllowedHttpException(['Recommender']);
        }

        switch ($action) {
            case 'pre_save':
                $name = RecommenderEvents::PRE_SAVE;
                break;
            case 'post_save':
                $name = RecommenderEvents::POST_SAVE;
                break;
            case 'pre_delete':
                $name = RecommenderEvents::PRE_DELETE;
                break;
            case 'post_delete':
                $name = RecommenderEvents::POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new RecommenderEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }

            $this->dispatcher->dispatch($name, $event);

            return $event;
        } else {
            return null;
        }
    }

    /**
     * @param        $type
     * @param string $filter
     * @param int    $limit
     * @param int    $start
     * @param array  $options
     */
    public function getLookupResults($type, $filter = '', $limit = 10, $start = 0, $options = [])
    {
        $results = [];
        switch ($type) {
            case 'recommender':
                $entities = $this->getRepository()->getRecommenderList(
                    $filter,
                    $limit,
                    $start,
                    $this->security->isGranted($this->getPermissionBase().':viewother'),
                    isset($options['top_level']) ? $options['top_level'] : false,
                    isset($options['ignore_ids']) ? $options['ignore_ids'] : []
                );

                foreach ($entities as $entity) {
                    $results[$entity['language']][$entity['id']] = $entity['name'];
                }

                //sort by language
                ksort($results);

                break;
        }

        return $results;
    }

    public function getRecommenderById(Recommender $id){
        $recommender = $this->getEntity($id);
    }
}
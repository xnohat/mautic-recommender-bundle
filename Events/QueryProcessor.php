<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecommenderBundle\Events;


use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticRecommenderBundle\Api\Service\ApiCommands;
use MauticPlugin\MauticRecommenderBundle\Model\RecommenderEventModel;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;

class QueryProcessor
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var CorePermissions
     */
    private $security;

    /**
     * @var ApiCommands
     */
    private $apiCommands;

    /**
     * @var EventModel
     */
    private $eventModel;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Processor constructor.
     *
     * @param CoreParametersHelper  $coreParametersHelper
     * @param CorePermissions       $security
     * @param ApiCommands           $apiCommands
     * @param RecommenderEventModel $eventModel
     * @param TranslatorInterface   $translator
     * @param LeadModel             $leadModel
     * @param EntityManager         $entityManager
     */
    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        CorePermissions $security,
        ApiCommands $apiCommands,
        RecommenderEventModel $eventModel,
        TranslatorInterface $translator,
        LeadModel $leadModel,
        EntityManager $entityManager
    ) {

        $this->coreParametersHelper = $coreParametersHelper;
        $this->security             = $security;
        $this->apiCommands          = $apiCommands;
        $this->eventModel           = $eventModel;
        $this->translator           = $translator;
        $this->leadModel            = $leadModel;
        $this->entityManager        = $entityManager;
    }

    /**
     * @param array|null $arrQuery
     *
     * @return bool
     * @throws \Exception
     */
    public function query($arrQuery)
    {
        if (empty($arrQuery)) {
            throw new \Exception('Event query fields cannot be empty');
        }

        $contact = $this->leadModel->checkForDuplicateContact(['email'=>$arrQuery['email']]);

        //check contact by Email
        if (isset($arrQuery['contactEmail'])) {
            $contact = $this->leadModel->checkForDuplicateContact(['email'=>$arrQuery['contactEmail']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$arrQuery['contactEmail'].' not found');
            }
            unset($arrQuery['contactEmail']);
            

        } elseif(isset($arrQuery['email'])){
            $contact = $this->leadModel->checkForDuplicateContact(['email'=>$arrQuery['email']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$arrQuery['email'].' not found');
            }
            unset($arrQuery['email']);
            
        
        //check contact by Phone
        } elseif(isset($arrQuery['contactPhone'])){
            $contact = $this->leadModel->checkForDuplicateContact(['phone'=>$arrQuery['contactPhone']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$arrQuery['contactPhone'].' not found');
            }
            unset($arrQuery['contactPhone']);
            

        } elseif(isset($arrQuery['phone'])){
            $contact = $this->leadModel->checkForDuplicateContact(['phone'=>$arrQuery['phone']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$arrQuery['phone'].' not found');
            }
            unset($arrQuery['phone']);
            
        
        //check contact by id_card
        } elseif(isset($arrQuery['id_card'])){
        $contact = $this->leadModel->checkForDuplicateContact(['id_card'=>$arrQuery['id_card']]);
        if (!$contact instanceof Lead) {
            throw new \Exception('Contact with ID_Card '.$arrQuery['id_card'].' not found');
        }
        unset($arrQuery['id_card']);
        

        //check contact by contactId
        } elseif (isset($arrQuery['contactId'])) {
            $contact = $this->leadModel->getEntity($arrQuery['contactId']);
        
        //Finally, only throw error exception if this current event is from Console or API. Skip throw error for Pixel because pixel can track anonymous contact by mautic fingerprint
        } elseif(defined('IN_MAUTIC_CONSOLE') || defined('IN_MAUTIC_API')) {
            throw new \Exception('One of parameters contactId/contactEmail/email/contactPhone/phone/id_card is required');
        }

        $events = $this->entityManager->getRepository('MauticRecommenderBundle:EventLog')->getEvents($contact, $arrQuery['limit_last_days']);

        return $events;
    }

}
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

class Processor
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
     * Processor constructor.
     *
     * @param CoreParametersHelper  $coreParametersHelper
     * @param CorePermissions       $security
     * @param ApiCommands           $apiCommands
     * @param RecommenderEventModel $eventModel
     * @param TranslatorInterface   $translator
     * @param LeadModel             $leadModel
     */
    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        CorePermissions $security,
        ApiCommands $apiCommands,
        RecommenderEventModel $eventModel,
        TranslatorInterface $translator,
        LeadModel $leadModel
    ) {

        $this->coreParametersHelper = $coreParametersHelper;
        $this->security             = $security;
        $this->apiCommands          = $apiCommands;
        $this->eventModel           = $eventModel;
        $this->translator           = $translator;
        $this->leadModel            = $leadModel;
    }

    /**
     * @param array|null $eventDetail
     *
     * @return bool
     * @throws \Exception
     */
    public function process($eventDetail)
    {
        if (empty($eventDetail)) {
            throw new \Exception('Event detail of tracking event cannot be empty');
        }

        $eventLabel = $this->coreParametersHelper->getParameter('eventLabel');

        if (!isset($eventDetail['eventName'])) {
            throw new \Exception(
                $this->translator->trans('mautic.plugin.recommender.eventName.not_found', [], 'validators')
            );
        } elseif (!$this->eventModel->getRepository()->findOneBy(['name' => $eventDetail['eventName']])) {
            throw new \Exception(
                $this->translator->trans(
                    'mautic.plugin.recommender.eventName.not_allowed',
                    [
                        '%eventName%' => $eventDetail['eventName'],
                    ],
                    'validators'
                )
            );
        }

        //check contact by Email
        if (isset($eventDetail['contactEmail'])) {
            $contact = $this->leadModel->checkForDuplicateContact(['email'=>$eventDetail['contactEmail']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$eventDetail['contactEmail'].' not found');
            }
            unset($eventDetail['contactEmail']);
            $this->leadModel->setSystemCurrentLead($contact);

        } elseif(isset($eventDetail['email'])){
            $contact = $this->leadModel->checkForDuplicateContact(['email'=>$eventDetail['email']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$eventDetail['email'].' not found');
            }
            unset($eventDetail['email']);
            $this->leadModel->setSystemCurrentLead($contact);
        
        //check contact by Phone
        } elseif(isset($eventDetail['contactPhone'])){
            $contact = $this->leadModel->checkForDuplicateContact(['phone'=>$eventDetail['contactPhone']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$eventDetail['contactPhone'].' not found');
            }
            unset($eventDetail['contactPhone']);
            $this->leadModel->setSystemCurrentLead($contact);

        } elseif(isset($eventDetail['phone'])){
            $contact = $this->leadModel->checkForDuplicateContact(['phone'=>$eventDetail['phone']]);
            if (!$contact instanceof Lead) {
                throw new \Exception('Contact '.$eventDetail['phone'].' not found');
            }
            unset($eventDetail['phone']);
            $this->leadModel->setSystemCurrentLead($contact);
        
        //check contact by id_card
        } elseif(isset($eventDetail['id_card'])){
        $contact = $this->leadModel->checkForDuplicateContact(['id_card'=>$eventDetail['id_card']]);
        if (!$contact instanceof Lead) {
            throw new \Exception('Contact with ID_Card '.$eventDetail['id_card'].' not found');
        }
        unset($eventDetail['id_card']);
        $this->leadModel->setSystemCurrentLead($contact);

        //check contact by contactId
        } elseif (isset($eventDetail['contactId'])) {
            $this->leadModel->setSystemCurrentLead($this->leadModel->getEntity($eventDetail['contactId']));
        
        //Finally, only throw error exception if this current event is from Console or API. Skip throw error for Pixel because pixel can track anonymous contact by mautic fingerprint
        } elseif(defined('IN_MAUTIC_CONSOLE') || defined('IN_MAUTIC_API')) {
            throw new \Exception('One of parameters contactId/contactEmail/email/contactPhone/phone/id_card is required');
        }

        $this->apiCommands->callCommand($eventLabel, $eventDetail);

        return true;
    }

}


<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecommenderBundle\Entity;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;
use MauticPlugin\MauticRecommenderBundle\Helper\SqlQuery;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\TimelineTrait;

/**
 * Class EventLogRepository
 * @package MauticPlugin\MauticRecommenderBundle\Entity
 */
class EventLogRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * @return string
     */
    public function getTableAlias()
    {
        return 'el';
    }

    /**
     * @param int $limit
     *
     * @return array
     */
    public function findMostActiveContacts($limit = 25)
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select('el.lead_id')
            ->from(MAUTIC_TABLE_PREFIX.'recommender_event_log', 'el')
            ->groupBy('el.lead_id')
            ->orderBy('COUNT(el.id)',' desc')
            ->setMaxResults($limit);
        return $qb->execute()->fetchAll();
    }

     /**
     * @param Lead         $contact     
     * @param array        $options
     *
     * @return array
     */
    public function getTimeLineEvents(Lead $contact, array $options = [])
    {
        $alias = $this->getTableAlias();
        $qb    = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select($alias.'.*')
            ->from(MAUTIC_TABLE_PREFIX.'recommender_event_log', $alias);
            
            

        if ($contact) {
            $qb->andWhere($alias.'.lead_id = :lead')
                ->setParameter('lead', $contact->getId());
        }
        
        if (!empty($options['search'])) {
            $qb->innerJoin($alias, 'recommender_event', 're', 're.id = '.$alias.'.event_id' );
            $qb->innerJoin($alias, 'recommender_item', 'ri', 'ri.id = '.$alias.'.item_id' );
            $qb->leftJoin('ri', 'recommender_item_property_value', 'ripv', 'ri.id = ripv.item_id');
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('re.name', $qb->expr()->literal('%'.$options['search'].'%')),
                    $qb->expr()->like('ripv.value', $qb->expr()->literal('%'.$options['search'].'%'))
                )
            );
            $qb->groupBy($alias.'.id');
        }

        return $this->getTimelineResults($qb, $options, 're.name', $alias.'.date_added', [], ['date_added']);
    }

    /**
     * @param Lead         $contact
     * @param int          $limit_last_days     
     *
     * @return array
     */
    public function getEvents(Lead $contact, $limit_last_days = 7)
    {
        $alias = $this->getTableAlias();
        $qb    = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select($alias.'.*', 're.name as event_name', 'relpv.id as event_property_value_id', 'rp_for_relpv.name as event_property_name', 'relpv.value as event_property_value', 'ripv.value as item_property_value', 'rp.name as item_property_name', "'".$contact->getName()."' as lead_name", "'".$contact->getEmail()."' as lead_email", "'".$contact->getLeadPhoneNumber()."' as lead_phone")
            ->from(MAUTIC_TABLE_PREFIX.'recommender_event_log', $alias);
            
        $qb->leftJoin($alias, 'recommender_event', 're', 're.id = '.$alias.'.event_id' );
        $qb->leftJoin($alias, 'recommender_item', 'ri', 'ri.id = '.$alias.'.item_id' );
        $qb->leftJoin('ri', 'recommender_item_property_value', 'ripv', 'ri.id = ripv.item_id');
        $qb->leftJoin('ripv', 'recommender_property', 'rp', 'rp.id = ripv.property_id');
        $qb->leftJoin($alias, 'recommender_event_log_property_value', 'relpv', 'relpv.event_log_id = '.$alias.'.id' );
        $qb->leftJoin('relpv', 'recommender_property', 'rp_for_relpv', 'rp_for_relpv.id = relpv.property_id');

        //Security by prepared SQL
        $qb->andWhere($alias.'.lead_id = :lead')
            ->setParameter('lead', $contact->getId());

        //limit results to last x days
        $qb->andWhere ($alias.'.date_added > DATE_SUB(CURDATE(), INTERVAL :limit_last_days DAY)')
            ->setParameter('limit_last_days', $limit_last_days);

        //$qb->groupBy($alias.'.id');
        $qb->orderBy($alias.'.id',' desc');

        return $qb->execute()->fetchAll();

    }

}

<?php

namespace BeneficiaryBundle\Repository;

use BeneficiaryBundle\Entity\Household;
use DistributionBundle\Entity\DistributionData;
use CommonBundle\Entity\Location;
use DistributionBundle\Repository\AbstractCriteriaRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use ProjectBundle\Entity\Project;

/**
 * BeneficiaryRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BeneficiaryRepository extends AbstractCriteriaRepository
{
    /**
     * Get all beneficiaries in a selected project.
     *
     * @param int $project
     *
     * @param string $target
     * @return mixed
     */
    public function getAllOfProject(int $project, string $target)
    {
        $qb = $this->createQueryBuilder('b');
        if ($target == 'Household') {
            $q = $qb->leftJoin('b.household', 'hh')
                ->where(':project MEMBER OF hh.projects')
                ->andWhere('b.status = 1')
                ->setParameter('project', $project);
        } else {
            $q = $qb->leftJoin('b.household', 'hh')
                ->where(':project MEMBER OF hh.projects')
                ->setParameter('project', $project);
        }

        return $q->getQuery()->getResult();
    }

    public function findByUnarchived(array $byArray)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.household', 'hh')
                ->where('hh.archived = 0');
        foreach ($byArray as $key => $value) {
            $q = $q->andWhere('b.' . $key . ' = :value' . $key)
                    ->setParameter('value' . $key, $value);
        }

        return $q->getQuery()->getResult();
    }

    /**
     * @param int $vulnerabilityId
     * @param string $conditionString
     * @param int $beneficiaryId
     * @return mixed
     */
    public function hasVulnerabilityCriterion(int $vulnerabilityId, string $conditionString, int $beneficiaryId)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.vulnerabilityCriteria', 'vc')
            ->andWhere('b.id = :beneficiaryId')
            ->setParameter(':beneficiaryId', $beneficiaryId);

        if ($conditionString == "true") {
            $q->andWhere(':vulnerabilityId = vc.id');
        } else {
            $orStatement = $q->expr()->orX();
            $orStatement->add($q->expr()->eq('SIZE(b.vulnerabilityCriteria)', 0))
                        ->add($q->expr()->neq(':vulnerabilityId', 'vc.id'));
            $q->andWhere($orStatement);
        }

        $q->setParameter('vulnerabilityId', $vulnerabilityId);

        return $q->getQuery()->getResult();
    }


     /**
     * @param string $fieldString
     * @param string $conditionString
     * @param string $valueString
     * @param int $beneficiaryId
     * @return mixed
     */
    public function hasParameter(string $fieldString, string $conditionString, string $valueString, int $beneficiaryId)
    {
        $qb = $this->createQueryBuilder('b');
        $column = 'b.' . $fieldString;

        if ($conditionString !== '!=') {
            $qb->where($column . $conditionString . ' :parameter '  );
        } else {
            $qb->where(':parameter <>' . $column);
        }

        $q = $qb->setParameter('parameter', $valueString)
            ->andWhere(':beneficiaryId = b.id')
            ->setParameter(':beneficiaryId', $beneficiaryId);

        return $q->getQuery()->getResult();
    }

    /**
     * @param string $valueString
     * @param int $beneficiaryId
     * @return mixed
     */
    public function lastDistributionAfter(string $valueString, int $beneficiaryId)
    {
        $qb = $this->createQueryBuilder('b');

        $q = $qb->leftJoin('b.distributionBeneficiary', 'db')
            ->leftJoin('db.distributionData', 'd')
            ->andWhere('SIZE(b.distributionBeneficiary) > 0')
            ->andWhere(':beneficiaryId = b.id')
            ->andWhere('d.dateDistribution >= :date')
            ->setParameter(':date', $valueString)
            ->setParameter(':beneficiaryId', $beneficiaryId);

        return $q->getQuery()->getResult();
    }

    public function getAllInCountry(string $iso3) {
        $qb = $this->createQueryBuilder('b');
        $this->beneficiariesInCountry($qb, $iso3);
        $qb->andWhere('hh.archived = 0');

        return $qb->getQuery()->getResult();
    }

    public function countAllInCountry(string $iso3)
    {
        $qb = $this->createQueryBuilder('b');
        $this->beneficiariesInCountry($qb, $iso3);
        $qb->andWhere('hh.archived = 0')
            ->select('COUNT(b)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getAllofDistribution(DistributionData $distributionData)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.distributionBeneficiary', 'db')
            ->where('db.distributionData = :distributionData')
            ->setParameter('distributionData', $distributionData);

        return $q->getQuery()->getResult();
    }

    public function getNotRemovedofDistribution(DistributionData $distributionData)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.distributionBeneficiary', 'db')
            ->where('db.distributionData = :distributionData')
            ->andWhere('db.removed = 0')
            ->setParameter('distributionData', $distributionData);

        return $q->getQuery()->getResult();
    }

    /**
     * Get the head of household.
     *
     * @param Household $household
     *
     * @return mixed
     */
    public function getHeadOfHousehold(Household $household)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->where('b.household = :household')
            ->andWhere('b.status = 1')
            ->setParameter('household', $household);

        try {
            return $q->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * Get the head of household.
     *
     * @param $householdId
     *
     * @return mixed
     */
    public function getHeadOfHouseholdId($householdId)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.household', 'hh')
            ->andWhere('hh.id = :id')
            ->andWhere('b.status = 1')
            ->setParameter('id', $householdId);

        try {
            return $q->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * @param $onlyCount
     * @param $countryISO3
     * @param Project $project
     *
     * @return QueryBuilder|void
     */
    public function configurationQueryBuilder($onlyCount, $countryISO3, Project $project = null)
    {
        $qb = $this->createQueryBuilder('b');

        if ($onlyCount) {
            $qb->select('count(b)');
        }
        if (null !== $project) {
            $qb->where(':idProject MEMBER OF hh.projects')
                ->setParameter('idProject', $project->getId());
        }
        $qb->leftJoin('b.household', 'hh');
        $this->setCountry($qb, $countryISO3);

        return $qb;
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must have at least one beneficiary with the condition respected ($field $operator $value / Example: gender = 0).
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    public function whereDefault(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qb->andWhere("b.{$filters['field_string']} {$filters['condition_string']} :val$i")
            ->setParameter("val$i", $filters['value_string']);
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must respect the value of the country specific ($idCountrySpecific), depends on operator and value.
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    protected function whereVulnerabilityCriterion(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qb->leftJoin('b.vulnerabilityCriteria', "vc$i")
            ->andWhere("vc$i.id = :idvc$i")
            ->setParameter("idvc$i", $filters['id_field']);
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must respect the value of the country specific ($idCountrySpecific), depends on operator and value.
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    protected function whereCountrySpecific(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qb->leftJoin('hh.countrySpecificAnswers', "csa$i")
            ->andWhere("csa$i.countrySpecific = :countrySpecific$i")
            ->setParameter("countrySpecific$i", $filters['id_field'])
            ->andWhere("csa$i.answer {$filters['condition_string']} :value$i")
            ->setParameter("value$i", $filters['value_string']);
    }

    public function countServedInCountry($iso3) {
        $qb = $this->createQueryBuilder('b');
        $this->beneficiariesInCountry($qb, $iso3);

        $qb->select('COUNT(DISTINCT b)')
            ->leftJoin('b.distributionBeneficiary', 'db')
            ->leftJoin('db.booklets', 'bk')
            ->leftJoin('db.transactions', 't')
            ->leftJoin('db.generalReliefs', 'gri')
            ->andWhere('t.transactionStatus = 1 OR gri.distributedAt IS NOT NULL OR bk.id IS NOT NULL');

        return $qb->getQuery()->getSingleScalarResult();
    }

    private function beneficiariesInCountry(QueryBuilder &$qb, $countryISO3) {
        $qb->leftJoin('b.household', 'hh');

        $householdRepository = $this->getEntityManager()->getRepository(Household::class);
        $householdRepository->whereHouseholdInCountry($qb, $countryISO3);
    }

    
    public function getDistributionBeneficiariesForHouseholds(
        array $criteria, 
        Project $project, 
        string $country, 
        int $threshold, 
        string $distributionTarget, 
        bool $count)
    {
        $hhRepository = $this->getEntityManager()->getRepository(Household::class);

        $qb = $hhRepository->createQueryBuilder("hh");
        $qb->leftJoin("hh.projects", "p")
            ->where("p = :project")
            ->setParameter("project", $project)
            ->andWhere("hh.archived = 0")
            ->leftJoin('hh.beneficiaries', 'b')
            ->leftJoin('hh.beneficiaries', 'head')
            ->andWhere('head.status = 1');

        if ($count) {
            $qb->select('COUNT(DISTINCT head)');
        } else {
            $qb->select('head.id AS id');
        }

        $orStatement = $qb->expr()->orX();
        foreach ($criteria as $index => $criterion) {
            $this->getDistributionBeneficiariesPerCriteria($criterion, $index, $country, $qb,  $orStatement);
        }
        $qb->andWhere($orStatement);

        if ($count) {
            return intval($qb->getQuery()->getSingleScalarResult());
        } else {
            return $qb->getQuery()->getResult();
        }
    }

    public function getDistributionBeneficiariesForBeneficiaries(
        array $criteria, 
        Project $project, 
        string $country, 
        int $threshold, 
        string $distributionTarget,
        bool $count)
    {
        $hhRepository = $this->getEntityManager()->getRepository(Household::class);

        $qb = $hhRepository->createQueryBuilder("hh");
        $qb->leftJoin("hh.projects", "p")
            ->where("p = :project")
            ->setParameter("project", $project)
            ->andWhere("hh.archived = 0")
            ->leftJoin('hh.beneficiaries', 'b');

        if ($count) {
            $qb->select('COUNT(DISTINCT b)');
        } else {
            $qb->select('b.id AS id');
        }

        $orStatement = $qb->expr()->orX();
        foreach ($criteria as $index => $criterion) {
            $this->getDistributionBeneficiariesPerCriteria($criterion, $index, $country, $qb,  $orStatement);
        }
        $qb->andWhere($orStatement);

        if ($count) {
            return intval($qb->getQuery()->getSingleScalarResult());
        } else {
            return $qb->getQuery()->getResult();
        }
    }


    public function getDistributionBeneficiariesPerCriteria(array $criterion, int $index, string $country, &$qb, &$orStatement)
    {
        $criterion['condition_string'] = $criterion['condition_string'] === '!=' ? '<>' : $criterion['condition_string'];
        if ($criterion['target'] == "Household") {
            $this->getHouseholdWithCriterion($qb, $criterion, $index, $orStatement);
        } elseif ($criterion['target'] == "Beneficiary") {
            $this->getBeneficiaryWithCriterion($qb, $criterion, $index, $orStatement);
        } elseif ($criterion['target'] == "Head") {
            $this->getHeadWithCriterion($qb, $criterion, $index, $orStatement);
        }
        $qb->setParameter('parameter' . $index, $criterion['value_string']);
    }

    
    private function getHouseholdWithCriterion(&$qb, $criterion, int $index, &$orStatement)
    {
        if ($criterion['type'] === 'table_field') {
            $orStatement->add('hh.' . $criterion['field_string'] . $criterion['condition_string'] . ' :parameter' . $index);
        }
        // The selection criteria is a country Specific
        else if ($criterion['type'] = 'BeneficiaryBundle\Entity\CountrySpecific') {
            $qb->leftJoin('h.countrySpecificAnswers', 'csa')
            ->leftJoin('csa.countrySpecific', 'cs')
            ->andWhere('cs.fieldString = ' . $criterion['field_string']);
            $orStatement->add('csa.answer ' . $criterion['condition_string'] . ' :parameter' . $index);
            // ->andWhere('csa.answer ' . $criterion['condition_string'] . ' :parameter' . $index);
        }
        else if ($criterion['type'] === 'other') {
            // The selection criteria is the size of the household
            if ($criterion['field_string'] === 'householdSize') {
                $orStatement->add('SIZE(b) ' . $criterion['condition_string'] . ' :parameter' . $index);
            }
            // The selection criteria is the location type (residence, camp...)
            else if ($criterion['field_string'] === 'locationType') {
                $qb->leftJoin('h.householdLocations', 'hl');
                $orStatement->add('hl.type ' . $criterion['condition_string'] . ' :parameter' . $index);
            } 
            // The selection criteria is the name of the camp in which the household lives
            else if ($criterion['field_string'] === 'campName') {
                $qb->leftJoin('h.householdLocations', 'hl')
                    ->leftJoin('hl.campAddress', 'ca')
                    ->leftJoin('ca.camp', 'c');
                $orStatement->add('c.name = :parameter' . $index);
            }
        }
    }

    private function getBeneficiaryWithCriterion(&$qb, $criterion, int $index, &$orStatement)
    {
        // Table_field means we can directly fetch the value in the DB
        if ($criterion['type'] === 'table_field') {
            $orStatement->add('b.' . $criterion['field_string'] . $criterion['condition_string'] . ' :parameter' . $index);
            $qb->addSelect('b.' . $criterion['field_string'] . ' AS ' . $criterion['field_string']);
        }
        // The selection criteria is a vulnerability criterion
        else if ($criterion['type'] === 'BeneficiaryBundle\Entity\VulnerabilityCriterion') {
            $this->hasVC($qb, 'b', $criterion['condition_string'], $criterion['field_string'], $orStatement);
        }       
        else if ($criterion['type'] === 'other') {
            // The selection criteria is the last distribution
            if ($criterion['field_string'] === 'hasNotBeenInDistributionsSince') {
                $qb->leftJoin('b.distributionBeneficiary', 'db')
                    ->leftJoin('db.distributionData', 'd')
                    ->andWhere('d.dateDistribution >= :parameter' . $index);
                $orStatement->add($qb->expr()->eq('SIZE(d)', 0));
            }
        }
    }

    private function getHeadWithCriterion(&$qb, $criterion, int $index, &$orStatement)
    {
        $qb->leftJoin('hh.beneficiaries', 'hhh')
            ->andWhere('hhh.status = 1');
        // Table_field means we can directly fetch the value in the DB
        if ($criterion['type'] === 'table_field') {
            if ($criterion['field_string'] === 'headOfHouseholdDateOfBirth') {
                $criterion['field_string'] = 'dateOfBirth';
            } else if ($criterion['field_string'] === 'headOfHouseholdGender') {
                $criterion['field_string'] = 'gender';
            }
            $qb->leftJoin('hhh.' . $criterion['field_string'], 'fsh');
            $orStatement->add('fsh ' . $criterion['condition_string'] . ' :parameter' . $index);
        }
        else if ($criterion['type'] === 'other') {
            if ($criterion['field_string'] === 'disabledHeadOfHousehold') {
                $this->hasVC($qb, 'hhh', $criterion['condition_string'], 'disabled', $orStatement);
            }
        }
    }

    private function hasVC(&$qb, $on, $conditionString, $vulnerabilityName, &$orStatement) {
        $qb->leftJoin($on . '.vulnerabilityCriteria', 'vc');
        if ($conditionString == "true") {
            $orStatement->add('vc.fieldString = ' . $vulnerabilityName);
        } else {
            $or = $qb->expr()->orX();
            $or->add($qb->expr()->eq('SIZE(vc)', 0))
                ->add($qb->expr()->neq($vulnerabilityName, 'vc.fieldString'));
                $orStatement->add($or);
        }
    }

}

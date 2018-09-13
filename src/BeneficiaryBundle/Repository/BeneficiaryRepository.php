<?php

namespace BeneficiaryBundle\Repository;

use BeneficiaryBundle\Entity\Household;
use DistributionBundle\Entity\DistributionData;
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
     * @return mixed
     */
    public function getAllOfProject(int $project)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.household', 'hh')
            ->where(':project MEMBER OF hh.projects')
            ->setParameter('project', $project);

        return $q->getQuery()->getResult();
    }

    public function countAllInCountry(string $iso3)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->select('COUNT(b)')
                ->leftJoin('b.household', 'hh')
                ->leftJoin('hh.location', 'l')
                ->leftJoin('l.adm1', 'adm1')
                ->leftJoin('l.adm2', 'adm2')
                ->leftJoin('l.adm3', 'adm3')
                ->leftJoin('l.adm4', 'adm4')
                ->where('adm1.countryISO3 = :iso3 AND hh.archived = 0')
                ->leftJoin('adm4.adm3', 'adm3b')
                ->leftJoin('adm3b.adm2', 'adm2b')
                ->leftJoin('adm2b.adm1', 'adm1b')
                ->orWhere('adm1b.countryISO3 = :iso3 AND hh.archived = 0')
                ->leftJoin('adm3.adm2', 'adm2c')
                ->leftJoin('adm2c.adm1', 'adm1c')
                ->orWhere('adm1c.countryISO3 = :iso3 AND hh.archived = 0')
                ->leftJoin('adm2.adm1', 'adm1d')
                ->orWhere('adm1d.countryISO3 = :iso3 AND hh.archived = 0')
                ->setParameter('iso3', $iso3);

        return $q->getQuery()->getSingleScalarResult();
    }

    public function getAllofDistribution(DistributionData $distributionData)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->leftJoin('b.distributionBeneficiary', 'db')
            ->where('db.distributionData = :distributionData')
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
}

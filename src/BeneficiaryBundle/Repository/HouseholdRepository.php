<?php

namespace BeneficiaryBundle\Repository;

use DistributionBundle\Repository\AbstractCriteriaRepository;
use Doctrine\ORM\QueryBuilder;
use ProjectBundle\Entity\Project;

/**
 * HouseholdRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class HouseholdRepository extends AbstractCriteriaRepository
{

    public function foundSimilarLevenshtein(string $stringToSearch, int $minimumTolerance)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.beneficiaries", "b")
            ->where("b.status = 1")
            ->andWhere("
                LEVENSHTEIN(
                    CONCAT(hh.addressStreet, hh.addressNumber, hh.addressPostcode, b.givenName, b.familyName),
                    :stringToSearch
                ) < :minimumTolerance
            ")
            ->setParameter("stringToSearch", $stringToSearch)
            ->setParameter("minimumTolerance", $minimumTolerance);

        return $q->getQuery()->getResult();
    }

    /**
     * Get all Household by country
     * Use $filters to add a offset and a limit. Default => offset = 0 and limit = 10
     * @param $iso3
     * @param array $filters
     * @param array $selects
     * @return mixed
     */
    public function getAllBy($iso3, $filters = [], $selects = [])
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.location", "l")
            ->where("l.countryIso3 = :iso3")
            ->setParameter("iso3", $iso3)
            ->andWhere("hh.archived = 0");
        if (array_key_exists("offset", $filters))
            $q->setMaxResults(intval($filters['limit']));
        if (array_key_exists("limit", $filters))
            $q->setFirstResult(intval($filters['offset']));

        if (!empty($selects))
        {
            $q->select(current($selects));
            while (next($selects) !== false ?: key($selects) !== null)
            {
                $q->addSelect($selects);
            }
        }

        return $q->getQuery()->getResult();
    }

    /**
     * Get similar household
     * @param array $householdArray
     * @return mixed
     */
    public function getSimilar(array $householdArray)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->where("SOUNDEX(hh.addressStreet) = SOUNDEX(:addr_street)")
            ->andWhere("SOUNDEX(hh.addressNumber) = SOUNDEX(:addr_number)")
            ->andWhere("SOUNDEX(hh.addressPostcode) = SOUNDEX(:addr_postcode)")
            ->setParameter("addr_street", $householdArray["address_street"])
            ->setParameter("addr_number", $householdArray["address_number"])
            ->setParameter("addr_postcode", $householdArray["address_postcode"]);

        return $q->getQuery()->getResult();
    }

    /**
     * @param $onlyCount
     * @param $countryISO3
     * @param $groupGlobal
     * @return QueryBuilder
     */
    public function configurationQueryBuilder($onlyCount, $countryISO3)
    {
        $qb = $this->createQueryBuilder("hh");

        if ($onlyCount)
            $qb->select("count(hh)");

        $qb->leftJoin("hh.beneficiaries", "b");
        $this->setCountry($qb, $countryISO3);

        return $qb;
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must have at least one beneficiary with the condition respected ($field $operator $value / Example: gender = 0)
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    public function whereDefault(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qbSub = $this->createQueryBuilder("hh$i");
        $this->setCountry($qbSub, $countryISO3, $i);
        $qbSub->leftJoin("hh$i.beneficiaries", "b$i")
            ->andWhere("b$i.{$filters["field_string"]} {$filters["condition_string"]} :val$i")
            ->setParameter("val$i", $filters["value_string"]);
        if (null !== $filters["kind_beneficiary"])
            $qbSub->andWhere("b$i.status = :status$i")
                ->setParameter("status$i", $filters["kind_beneficiary"]);

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("val$i", $filters["value_string"]);
        if (null !== $filters["kind_beneficiary"])
            $qb->setParameter("status$i", $filters["kind_beneficiary"]);
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must respect the value of the country specific ($idCountrySpecific), depends on operator and value
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    protected function whereVulnerabilityCriterion(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qbSub = $this->createQueryBuilder("hh$i");
        $this->setCountry($qbSub, $countryISO3, $i);
        $qbSub->leftJoin("hh$i.beneficiaries", "b$i");
        if (boolval($filters["condition_string"]))
        {
            $qbSub->leftJoin("b$i.vulnerabilityCriteria", "vc$i")
                ->andWhere("vc$i.id = :idvc$i")
                ->setParameter("idvc$i", $filters["id_field"]);
        }
        else
        {
            $qbSubNotIn = $this->createQueryBuilder("hhb$i");
            $this->setCountry($qbSubNotIn, $countryISO3, "b$i");
            $qbSubNotIn->leftJoin("hhb$i.beneficiaries", "bb$i")
                ->leftJoin("bb$i.vulnerabilityCriteria", "vcb$i")
                ->andWhere("vcb$i.id = :idvc$i")
                ->setParameter("idvc$i", $filters["id_field"]);

            $qbSub->andWhere($qbSub->expr()->notIn("hh$i", $qbSubNotIn->getDQL()));
        }

        if (null !== $filters["kind_beneficiary"])
        {
            $qbSub->andWhere("b$i.status = :status$i")
                ->setParameter("status$i", $filters["kind_beneficiary"]);
        }

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("idvc$i", $filters["id_field"])
            ->setParameter("status$i", $filters["kind_beneficiary"]);
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must respect the value of the country specific ($idCountrySpecific), depends on operator and value
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    protected function whereCountrySpecific(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qbSub = $this->createQueryBuilder("hh$i");
        $this->setCountry($qbSub, $countryISO3, $i);
        $qbSub->leftJoin("hh$i.countrySpecificAnswers", "csa$i")
            ->andWhere("csa$i.countrySpecific = :countrySpecific$i")
            ->setParameter("countrySpecific$i", $filters["id_field"])
            ->andWhere("csa$i.answer {$filters["condition_string"]} :value$i")
            ->setParameter("value$i", $filters["value_string"]);

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("value$i", $filters["value_string"])
            ->setParameter("countrySpecific$i", $filters["id_field"]);
    }

    /**
     * Set the country iso3 in the query on Household (with alias 'hh{id}'
     *
     * @param QueryBuilder $qb
     * @param $countryISO3
     * @param string $i
     */
    protected function setCountry(QueryBuilder &$qb, $countryISO3, $i = '')
    {
        $qb->leftJoin("hh$i.location", "l$i")
            ->andWhere("l$i.countryIso3 = :countryIso3")
            ->setParameter("countryIso3", $countryISO3);
    }

    /**
     * count the number of housholds linked to a project
     *
     * @param Project $project
     * @return
     */
    public function countByProject(Project $project)
    {
        $qb = $this->createQueryBuilder("hh");
        $qb->select("count(hh)")
            ->leftJoin("hh.projects", "p")
            ->andWhere("p = :project")
            ->setParameter("project", $project);

        return $qb->getQuery()->getResult()[0];
    }
}

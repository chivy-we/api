<?php

namespace BeneficiaryBundle\Repository;

use DistributionBundle\Repository\AbstractCriteriaRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Query\Expr\Join;
use ProjectBundle\Entity\Project;
use CommonBundle\Entity\Adm3;
use CommonBundle\Entity\Adm2;
use CommonBundle\Entity\Adm1;

/**
 * HouseholdRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class HouseholdRepository extends AbstractCriteriaRepository
{
    /**
     * Find all households in country
     * @param  string $iso3
     * @return QueryBuilder
     */
    public function findAllByCountry(string $iso3)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.location", "l")
            ->leftJoin("l.adm1", "adm1")
            ->leftJoin("l.adm2", "adm2")
            ->leftJoin("l.adm3", "adm3")
            ->leftJoin("l.adm4", "adm4")
            ->where("adm1.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm4.adm3", "adm3b")
            ->leftJoin("adm3b.adm2", "adm2b")
            ->leftJoin("adm2b.adm1", "adm1b")
            ->orWhere("adm1b.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm3.adm2", "adm2c")
            ->leftJoin("adm2c.adm1", "adm1c")
            ->orWhere("adm1c.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm2.adm1", "adm1d")
            ->orWhere("adm1d.countryISO3 = :iso3 AND hh.archived = 0")
            ->setParameter("iso3", $iso3);
        
        return $q;
    }
    
    public function getUnarchivedByProject(Project $project)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.projects", "p")
                ->where("p = :project")
                ->setParameter("project", $project)
                ->andWhere("hh.archived = 0");
                
        return $q->getQuery()->getResult();
    }
    
    public function countUnarchivedByProject(Project $project)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->select("COUNT(hh)")
                ->leftJoin("hh.projects", "p")
                ->where("p = :project")
                ->setParameter("project", $project)
                ->andWhere("hh.archived = 0");

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Return households which a Levenshtein distance with the stringToSearch under minimumTolerance
     * @param string $iso3
     * @param string $stringToSearch
     * @param int $minimumTolerance
     * @return mixed
     */
    public function foundSimilarLevenshtein(string $iso3, string $stringToSearch, int $minimumTolerance)
    {
        $qb = $this->findAllByCountry($iso3);
        $q = $qb->leftJoin("hh.beneficiaries", "b")
            ->select("hh as household")
            ->andWhere("hh.archived = 0")
            ->addSelect(
                "LEVENSHTEIN(
                    CONCAT(
                        COALESCE(hh.addressStreet, ''),
                        COALESCE(hh.addressNumber, ''),
                        COALESCE(hh.addressPostcode, ''),
                        COALESCE(b.localGivenName, ''),
                        COALESCE(b.localFamilyName, '')
                    ),
                    :stringToSearch
                ) as levenshtein")
            ->andWhere("b.status = 1")
            ->groupBy("b")
            ->having("levenshtein <= :minimumTolerance")
            ->setParameter("stringToSearch", $stringToSearch)
            ->setParameter("minimumTolerance", $minimumTolerance)
            ->orderBy("levenshtein", "ASC");

        return $q->getQuery()->getResult();
    }

    

    /**
     * Get all Household by country
     * @param $iso3
     * @param $begin
     * @param $pageSize
     * @param $sort
     * @param array $filters
     * @return mixed
     */
    public function getAllBy($iso3, $begin, $pageSize, $sort, $filters = [])
    {
        // Recover global information for the page
        $qb = $this->createQueryBuilder("hh");

        // Join all location tables (not just the one in the location)
        $q = $qb->innerJoin("hh.location", "l")
                ->leftJoin("l.adm4", "adm4")
                ->leftJoin("l.adm3", "locAdm3")
                ->leftJoin("l.adm2", "locAdm2")
                ->leftJoin("l.adm1", "locAdm1")
                ->leftJoin(Adm3::class, "adm3", Join::WITH, "adm3.id = COALESCE(IDENTITY(adm4.adm3, 'id'), locAdm3.id)")
                ->leftJoin(Adm2::class, "adm2", Join::WITH, "adm2.id = COALESCE(IDENTITY(adm3.adm2, 'id'), locAdm2.id)")
                ->leftJoin(Adm1::class, "adm1", Join::WITH, "adm1.id = COALESCE(IDENTITY(adm2.adm1, 'id'), locAdm1.id)")
                ->where("adm1.countryISO3 = :iso3")
                ->setParameter("iso3", $iso3)
                ->andWhere("hh.archived = 0");

        // We join information that is needed for the filters
        $q->leftJoin("hh.beneficiaries", "b")
            ->andWhere("hh.id = b.household")
            ->leftJoin("b.vulnerabilityCriteria", "vb")
            ->leftJoin("hh.projects", "p")
            ->leftJoin("b.referral", "r");
            
        // If there is a sort, we recover the direction of the sort and the field that we want to sort
        if (array_key_exists("sort", $sort) && array_key_exists("direction", $sort)) {
            $value = $sort["sort"];
            $direction = $sort["direction"];

            // If the field is the location, we sort it by the direction sent
            if ($value == "location") {
                $q->addGroupBy("adm1")->addOrderBy("adm1.name", $direction);
            }
            // If the field is the local first name, we sort it by the direction sent
            elseif ($value == "localFirstName") {
                $q->addGroupBy("b")->addOrderBy("b.localGivenName", $direction);
            }
            // If the field is the local family name, we sort it by the direction sent
            elseif ($value == "localFamilyName") {
                $q->addGroupBy("b")->addOrderBy("b.localFamilyName", $direction);
            }
            // If the field is the number of dependents, we sort it by the direction sent
            elseif ($value == "dependents") {
                $q->addGroupBy("b.household")->addOrderBy("COUNT(b.household)", $direction);
            }
            // If the field is the projects, we sort it by the direction sent
            elseif ($value == "projects") {
                $q->addGroupBy("p")->addOrderBy("p.name", $direction);
            }
            // If the field is the vulnerabilities, we sort it by the direction sent
            elseif ($value == "vulnerabilities") {
                $q->addGroupBy("vb")->addOrderBy("vb.fieldString", $direction);
            }

            $q->addGroupBy("hh.id");
        }

        // If there is a filter array in the request
        if (count($filters) > 0) {
            // For each filter in our array, we recover an index (to avoid parameters' repetitions in the WHERE clause) and the filters
            foreach ($filters as $indexFilter => $filter) {
                // We recover the category of the filter chosen and the value of the filter
                $category = $filter["category"];
                $filterValues = $filter["filter"];

                if ($category === "any" && count($filterValues) > 0) {
                    foreach ($filterValues as $filterValue) {
                        $q->andWhere("CONCAT(
                            COALESCE(b.enFamilyName, ''),
                            COALESCE(b.enGivenName, ''),
                            COALESCE(b.localFamilyName, ''),
                            COALESCE(b.localGivenName, ''),
                            COALESCE(p.name, ''),
                            COALESCE(adm1.name, ''),
                            COALESCE(adm2.name, ''),
                            COALESCE(adm3.name, ''),
                            COALESCE(adm4.name, ''),
                            COALESCE(vb.fieldString, '')
                        ) LIKE '%" . $filterValue . "%'");
                    }
                }
                elseif ($category === "gender") {
                    // If the category is the gender only one option can be selected and filterValues is a string instead of an array
                    $q->andWhere("b.gender = :filterValue")
                        ->setParameter("filterValue", $filterValues);
                }
                elseif ($category === "projects" && count($filterValues) > 0) {
                    $orStatement = $q->expr()->orX();
                    foreach ($filterValues as $indexValue => $filterValue) {
                        $q->setParameter("filter" . $indexFilter . $indexValue, $filterValue);
                        $orStatement->add($q->expr()->eq("p.id", ":filter" . $indexFilter . $indexValue));
                    }
                    $q->andWhere($orStatement);
                }
                elseif ($category === "vulnerabilities" && count($filterValues) > 0) {
                    $orStatement = $q->expr()->orX();
                    foreach ($filterValues as $indexValue => $filterValue) {
                        $q->setParameter("filter" . $indexFilter . $indexValue, $filterValue);
                        $orStatement->add($q->expr()->eq("vb.id", ":filter" . $indexFilter . $indexValue));
                    }
                    $q->andWhere($orStatement);
                }
                elseif ($category === "residency" && count($filterValues) > 0) {
                    $orStatement = $q->expr()->orX();
                    foreach ($filterValues as $indexValue => $filterValue) {
                        $q->setParameter("filter" . $indexFilter . $indexValue, $filterValue);
                        $orStatement->add($q->expr()->eq("b.residencyStatus", ":filter" . $indexFilter . $indexValue));
                    }
                    $q->andWhere($orStatement);
                } elseif ($category === "referral" && count($filterValues) > 0) {
                    $orStatement = $q->expr()->orX();
                    foreach ($filterValues as $indexValue => $filterValue) {
                        $q->setParameter("filter" . $indexFilter . $indexValue, $filterValue);
                        $orStatement->add($q->expr()->eq("r.type", ":filter" . $indexFilter . $indexValue));
                    }
                    $q->andWhere($orStatement);
                }
                elseif ($category === "livelihood" && count($filterValues) > 0) {
                    $orStatement = $q->expr()->orX();
                    foreach ($filterValues as $indexValue => $filterValue) {
                        $q->setParameter("filter" . $indexFilter . $indexValue, $filterValue);
                        $orStatement->add($q->expr()->eq("hh.livelihood", ":filter" . $indexFilter . $indexValue));
                    }
                    $q->andWhere($orStatement);
                }
                elseif ($category === "locations") {
                    // If the category is the location, filterValues is an array of adm ids
                    foreach($filterValues as $adm => $id) {
                        $q->andWhere($adm . " = :id" . $indexFilter)
                            ->setParameter("id" . $indexFilter, $id);
                    }
                }
            }
        }

        if (is_null($begin)) {
            $begin = 0;
        }
        if (is_null($pageSize)) {
            $pageSize = 0;
        }

        $q->setFirstResult($begin)
            ->setMaxResults($pageSize);

        $paginator = new Paginator($q, $fetchJoinCellection = true);

        return [count($paginator), $paginator->getQuery()->getResult()];
    }

    /**
     * Get all Household by country and id
     * @param string $iso3
     * @param array  $ids
     * @return mixed
     */
    public function getAllByIds(string $iso3, array $ids)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.location", "l")
            ->leftJoin("l.adm1", "adm1")
            ->leftJoin("l.adm2", "adm2")
            ->leftJoin("l.adm3", "adm3")
            ->leftJoin("l.adm4", "adm4")
            ->where("adm1.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm4.adm3", "adm3b")
            ->leftJoin("adm3b.adm2", "adm2b")
            ->leftJoin("adm2b.adm1", "adm1b")
            ->orWhere("adm1b.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm3.adm2", "adm2c")
            ->leftJoin("adm2c.adm1", "adm1c")
            ->orWhere("adm1c.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm2.adm1", "adm1d")
            ->orWhere("adm1d.countryISO3 = :iso3 AND hh.archived = 0")
            ->setParameter("iso3", $iso3);
        
        $q = $q->andWhere("hh.id IN (:ids)")
                ->setParameter("ids", $ids);

        return $q->getQuery()->getResult();
    }

    /**
     * @param $onlyCount
     * @param $countryISO3
     * @param Project $project
     * @return QueryBuilder|void
     */
    public function configurationQueryBuilder($onlyCount, $countryISO3, Project $project = null)
    {
        $qb = $this->createQueryBuilder("hh");
        if ($onlyCount) {
            $qb->select("count(hh)");
        }

        if (null !== $project) {
            $qb->where(":idProject MEMBER OF hh.projects")
                ->setParameter("idProject", $project->getId());
        }
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
        if (null !== $filters["kind_beneficiary"]) {
            $qbSub->andWhere("b$i.status = :status$i")
                ->setParameter("status$i", $filters["kind_beneficiary"]);
        }

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("val$i", $filters["value_string"]);
        if (null !== $filters["kind_beneficiary"]) {
            $qb->setParameter("status$i", $filters["kind_beneficiary"]);
        }
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
        if (boolval($filters["condition_string"])) {
            $qbSub->leftJoin("b$i.vulnerabilityCriteria", "vc$i")
                ->andWhere("vc$i.id = :idvc$i")
                ->setParameter("idvc$i", $filters["id_field"]);
        } else {
            $qbSubNotIn = $this->createQueryBuilder("hhb$i");
            $this->setCountry($qbSubNotIn, $countryISO3, "b$i");
            $qbSubNotIn->leftJoin("hhb$i.beneficiaries", "bb$i")
                ->leftJoin("bb$i.vulnerabilityCriteria", "vcb$i")
                ->andWhere("vcb$i.id = :idvc$i")
                ->setParameter("idvc$i", $filters["id_field"]);

            $qbSub->andWhere($qbSub->expr()->notIn("hh$i", $qbSubNotIn->getDQL()));
        }

        if (null !== $filters["kind_beneficiary"]) {
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
}

<?php

namespace ReportingBundle\Utils\DataRetrievers;

use Doctrine\ORM\EntityManager;

use ReportingBundle\Entity\ReportingProject;

/**
 * Class ProjectDataRetriever
 * @package ReportingBundle\Utils\DataRetrievers
 */
class ProjectDataRetriever extends AbstractDataRetriever
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * ProjectDataRetriever constructor.
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Use to make join and where in DQL
     * Use in all project data retrievers
     * @param string $code
     * @param array $filters
     * @return \Doctrine\ORM\QueryBuilder|mixed
     */
    public function getReportingValue(string $code, array $filters)
    {
        $qb = $this->em->createQueryBuilder()
                        ->from(ReportingProject::class, 'rp')
                        ->leftjoin('rp.value', 'rv')
                        ->leftjoin('rp.indicator', 'ri')
                        ->leftjoin('rp.project', 'p')
                        ->where('ri.code = :code')
                        ->setParameter('code', $code)
                        ->andWhere('p.iso3 = :country')
                        ->setParameter('country', $filters['country']);

        $qb = $this->filterByPeriod($qb, $filters['period']);

        $qb = $this->filterByProjects($qb, $filters['projects']);

        return $qb;
    }

    /**
     * switch case to use the right select
     * each case is the name of the function to execute
     *
     * Indicators with the same 'select' statement are grouped in the same case
     * @param $qb
     * @param $nameFunction
     * @param $frequency
     * @return mixed
     */
    public function conditionSelect($qb, $nameFunction)
    {
        switch ($nameFunction) {
            case 'BMS_Project_HS':
                $qb->select('p.name AS name')
                    ->groupBy('name');
                break;
            case 'BMSU_Project_NM':
            case 'BMSU_Project_NW':
                $qb->select("CONCAT(rv.unity, '/', p.name) AS name, p.name AS project")
                        ->groupBy('name', 'project');
                break;
            case 'BMSU_Project_PV':
                $qb->select('p.name AS name', 'p.id AS id')
                    ->groupBy('name', 'id');
                break;
            default:
                $qb->select('');
        }

        return $qb;
    }

    /**
     * Get the name of all donors
     * @param array $filters
     * @return array
     */
    public function BMS_Project_D(array $filters)
    {
        $qb = $this->getReportingValue('BMS_Project_D', $filters);
        $qb->select('p.name AS name', 'rv.value AS value')
            ->groupBy('value');
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the number of household served
     * @param array $filters
     * @return array
     */
    public function BMS_Project_HS(array $filters)
    {
        $qb = $this->getReportingValue('BMS_Project_HS', $filters);
        $qb = $this->conditionSelect($qb, 'BMS_Project_HS');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }

    /**
     * Get the beneficiaries age
     * @param array $filters
     * @return array
     */
    public function BMS_Project_AB(array $filters)
    {
        $qb = $this->getReportingValue('BMS_Project_AB', $filters);
        $qb = $this->conditionSelect($qb, 'BMS_Project_AB');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }

    /**
     * Get the number of men and women in a project
     * @param array $filters
     * @return array
     */
    public function BMS_Project_NMW(array $filters)
    {
        $men = $this->BMSU_Project_NM($filters);
        $women = $this->BMSU_Project_NW($filters);

        return array_merge($men, $women);
    }

    /**
     * Get the percentage of vulnerabilities served
     * @param array $filters
     * @return array
     */
    public function BMS_Project_PVS(array $filters)
    {
        $totalVulnerabilitiesServed = $this->BMSU_Project_TVS($filters);
        $vulnerabilitiesServedPerVulnerability = $this->BMSU_Project_TVSV($filters);

        // Map total number of vulnerabilities served to the date
        foreach ($totalVulnerabilitiesServed as $key => $total) {
            $totalVulnerabilitiesServed[$total['date']] = $total;
            unset($totalVulnerabilitiesServed[$key]);
        }

        foreach ($vulnerabilitiesServedPerVulnerability as $key => $vulnerability) {
            $percentageValue = (int)$vulnerability['value'] / (int)$totalVulnerabilitiesServed[$vulnerability['date']]['value'] * 100;
            $vulnerabilitiesServedPerVulnerability[$key]['value'] = $percentageValue;
        }

        return $vulnerabilitiesServedPerVulnerability;
    }


    /**
     * Utils indicators
     */


    /**
     * Get the number of men
     * @param array $filters
     * @return mixed
     */
    public function BMSU_Project_NM(array $filters)
    {

        $qb = $this->getReportingValue('BMSU_Project_NM', $filters);
        $qb = $this->conditionSelect($qb, 'BMSU_Project_NM');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }

    /**
     * Get the number of women
     * @param array $filters
     * @return mixed
     */
    public function BMSU_Project_NW(array $filters)
    {
        $qb = $this->getReportingValue('BMSU_Project_NW', $filters);
        $qb = $this->conditionSelect($qb, 'BMSU_Project_NW');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }

    /**
     * Get the total of vulnerabilities served by vulnerabilities
     * @param array $filters
     * @return mixed
     */
    public function BMSU_Project_TVSV(array $filters)
    {
        $qb = $this->getReportingValue('BMSU_Project_TVSV', $filters);
        $qb = $this->conditionSelect($qb, 'BMSU_Project_TVSV');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }

    /**
     * Get the total of vulnerabilities served
     * @param array $filters
     * @return mixed
     */
    public function BMSU_Project_TVS(array $filters)
    {
        $qb = $this->getReportingValue('BMSU_Project_TVS', $filters);
        $qb = $this->conditionSelect($qb, 'BMSU_Project_TVS');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }

    /**
     * Get the total of value in a project
     * @param array $filters
     * @return mixed
     */
    public function BMSU_Project_PV(array $filters)
    {
        $qb = $this->getReportingValue('BMSU_Project_PV', $filters);
        $qb = $this->conditionSelect($qb, 'BMSU_Project_PV');
        $result = $this->formatByFrequency($qb, $filters['frequency']);
        return $result;
    }
}

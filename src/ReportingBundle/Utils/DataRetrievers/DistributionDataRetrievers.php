<?php

namespace ReportingBundle\Utils\DataRetrievers;

use Doctrine\ORM\EntityManager;

use ReportingBundle\Entity\ReportingDistribution;
use \ProjectBundle\Entity\Project;
use \DistributionBundle\Entity\DistributionData;

class DistributionDataRetrievers
{
    private $em;
    private $reportingDistribution;
    private $project;

    public function __construct(EntityManager $em, ProjectDataRetrievers $project)
    {
        $this->em = $em;   
        $this->reportingDistribution = $em->getRepository(ReportingDistribution::class);
        $this->project = $project;
    }

     /**
     * Use to verify if a key project exist in filter
     * If this key exists, it means a project was selected in selector
     * In distribtuion mode, only one project could be selected
     */
    public function ifInProject($qb, array $filters) {
        if(array_key_exists('project', $filters)) {
            $qb->andWhere('p.id IN (:projects)')
                    ->setParameter('projects', $filters['project']);
        }
        $qb = $this->ifInDistribution($qb, $filters);
        return $qb;
    }

    /**
     * Use to verify if a key distribution exist in filter
     * If this key exists, it means a distribution was selected in selector
     */
    public function ifInDistribution($qb, array $filters) {
        if(array_key_exists('distribution', $filters)) {
            $qb->andWhere('d.id IN (:distributions)')
                    ->setParameter('distributions', $filters['distribution']);
        }
        return $qb;
    }

    /**
     * Use to make join and where in DQL
     * Use in all distribution data retrievers
     */
    public function getReportingValue(string $code, array $filters) {
        $qb = $this->reportingDistribution->createQueryBuilder('rd')
                                          ->leftjoin('rd.value', 'rv')
                                          ->leftjoin('rd.indicator', 'ri')
                                          ->leftjoin('rd.distribution', 'd')
                                          ->leftjoin('d.project', 'p')
                                          ->where('ri.code = :code')
                                          ->setParameter('code', $code)
                                          ->andWhere('p.iso3 = :country')
                                          ->setParameter('country', $filters['country']);
        $qb = $this->ifInProject($qb, $filters);
        return $qb;
    }

    /**
     * Get the data with the more recent values
     */
    public function lastDate(array $values) {
        $moreRecentValues = [];
        $lastDate = $values[0]['date'];
        foreach($values as $value) {
            if ($value['date'] > $lastDate) {
                $lastDate = $value['date'];
            }
        }
        foreach($values as $value) {
            if ($value['date'] === $lastDate) {
                array_push($moreRecentValues, $value);
            }
        }
        return $moreRecentValues;
    }

    /**
     * Get the number of enrolled beneficiaries in a distribution
     */
    public function BMS_Distribution_NEB(array $filters) {
        $qb = $this->getReportingValue('BMS_Distribution_NEB', $filters);
        $qb->select('d.name AS name','rv.value AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        $result = $this->lastDate($qb->getQuery()->getArrayResult());
        return $result;
    }

    /**
     * Get the total distribution value in a distribution
     */
    public function BMS_Distribution_TDV(array $filters) {
        $qb = $this->getReportingValue('BMS_Distribution_TDV', $filters);
        $qb->select('d.name AS name', 'd.id AS id','SUM(rv.value) AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
            ->groupBy('name', 'id', 'date');
        $results = $this->lastDate($qb->getQuery()->getArrayResult());
        return $results;
    }

    /**
     * Get the modality(and it type) for a distribution
     */
    public function BMS_Distribution_M(array $filters) {
        $qb = $this->getReportingValue('BMS_Distribution_M', $filters);
        $qb->select('d.name AS name','rv.value AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        $result = $this->lastDate($qb->getQuery()->getArrayResult());
        return $result;
    }

    /**
     * Get the age breakdown in a distribution
     */
    public function BMS_Distribution_AB(array $filters) {
        $qb = $this->getReportingValue('BMS_Distribution_AB', $filters);
        $qb->select('SUM(rv.value) AS value', 'rv.unity AS name', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
           ->groupBy('name', 'date');
        $result = $this->lastDate($qb->getQuery()->getArrayResult());;
        return $result;
    }

    /**
     * Get the number of men in a distribution
     */
    public function BMSU_Distribution_NM(array $filters) {
        $qb = $this->getReportingValue('BMSU_Distribution_NM', $filters);
        $qb->select("CONCAT(rv.unity, '/', d.name) AS name",'rv.value AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the number of women in a distribution
     */
    public function BMSU_Distribution_NW(array $filters) {
        $qb = $this->getReportingValue('BMSU_Distribution_NW', $filters);
        $qb->select("CONCAT(rv.unity, '/', d.name) AS name",'rv.value AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the number of men and women in a project
     */
    public function BMS_Distribution_NMW(array $filters) {

        $menAndWomen = [];
        $mens = $this->BMSU_Distribution_NM($filters);
        $womens = $this->BMSU_Distribution_NW($filters);
        $lastDate = $mens[0]['date'];
        foreach($mens as $men) {
            if ($men['date'] > $lastDate) {
                $lastDate = $men['date'];
            }
        }
        foreach ($mens as $men) { 
            if ($men["date"] == $lastDate) {
                $result = [
                    'name' => $men["name"],
                    'project' => substr($men["name"],4),
                    'value' => $men["value"],
                    'date' => $men['date']
                ]; 
                array_push($menAndWomen, $result);
                foreach ($womens as $women) {

                    if (substr($women["name"],6) == substr($men["name"], 4)) {
                        if ($women["date"] == $lastDate) {
                            $result = [
                                'name' => $women["name"],
                                'project' => substr($women["name"],6),
                                'value' => $women['value'],
                                'date' => $women['date']
                            ]; 
                            array_push($menAndWomen, $result);
                            break 1;
                        }
                    }  
                }                
            }   
        }
        return $menAndWomen; 
    }

    /**
     * Get the total of vulnerabilities served
     */
    public function BMSU_Distribution_TVS(array $filters) {
        $qb = $this->getReportingValue('BMSU_Distribution_TVS', $filters);
        $qb->select('SUM(rv.value) AS value', 'rv.unity AS unity',  "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
           ->groupBy('unity', 'date');         
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the total of vulnerabilities served by vulnerabilities
     */
    public function BMSU_Distribution_TVSV(array $filters) {
        $qb = $this->getReportingValue('BMSU_Distribution_TVSV', $filters);
        $qb->select('SUM(rv.value) AS value', 'rv.unity AS unity',  "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
           ->groupBy('unity', 'date');
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the percentage of vulnerabilities served
     */
    public function BMS_Distribution_PVS(array $filters) {
        $vulnerabilitiesPercentage = [];
        $totalVulnerabilities = $this->BMSU_Distribution_TVS($filters);
        $totalVulnerabilitiesByVulnerabilities = $this->BMSU_Distribution_TVSV($filters);
        $lastDate = $totalVulnerabilities[0]['date'];
        foreach($totalVulnerabilities as $totalVulnerability) {
            if ($totalVulnerability['date'] > $lastDate) {
                $lastDate = $totalVulnerability['date'];
            }
        }
        foreach ($totalVulnerabilities as $totalVulnerability) { 
            if ($totalVulnerability["date"] == $lastDate) {
                foreach ($totalVulnerabilitiesByVulnerabilities as $vulnerability) {
                    if ($vulnerability["date"] == $lastDate) {
                        $percent = ($vulnerability["value"]/$totalVulnerability["value"])*100;
                        $result = [
                            'name' => $vulnerability["unity"],
                            'value' => $percent,
                            'date' => $vulnerability['date']
                        ]; 
                        array_push($vulnerabilitiesPercentage, $result);
                    }   
                }                
            }   
        }
        return $vulnerabilitiesPercentage; 
    }

    /**
     * Get the percent of value used in the project by the distribution
     */
    public function BMS_Distribution_PPV(array $filters) {
        $projectDistributionValue =[];

        $repositoryProject = $this->em->getRepository(Project::class);

        $projectValue = $this->project->BMSU_Project_PV($filters);
        $moreRecentProject = $this->lastDate($projectValue);

        $distributionValue = $this->BMS_Distribution_TDV($filters);
        $moreRecentDistribution = $this->lastDate($distributionValue);

        $percentValueUsed = 0;
        foreach($moreRecentProject as $project) { 
            
            $findProject = $repositoryProject->findOneBy(['id' => $project['id']]); 
            foreach($moreRecentDistribution as $distribution) {
                foreach($findProject->getDistributions() as $findDistribution) {
                    if($distribution['id'] ===  $findDistribution->getId()) {
                        $percent = ($distribution["value"]/$findProject->getValue())*100;
                        $percentValueUsed = $percentValueUsed + $percent;
                        $result = [
                            'name' =>$findDistribution->getName(),
                            'value' => $distribution["value"].' ('.$percent.'%)',
                            'date' => $distribution['date']
                        ]; 
                        array_push($projectDistributionValue, $result);
                    }
                }    
            }

            $valueProjectUsed = ($findProject->getValue() * ($percentValueUsed/100));
            $result = [
                'name' => 'Value not used',
                'value' => $valueProjectUsed.' ('.$percentValueUsed.'%)',
                'date' => $project['date']
            ];
            array_push($projectDistributionValue, $result);
        }
         
        return $projectDistributionValue;
        
    }

}
<?php

namespace CommonBundle\Utils;

use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\Household;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use BeneficiaryBundle\Entity\NationalId;
use BeneficiaryBundle\Entity\Phone;
use BeneficiaryBundle\Entity\Profile;
use BeneficiaryBundle\Entity\VulnerabilityCriterion;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class ExportService
 * @package CommonBundle\Utils
 */
Class ExportService {

    /** @var EntityManagerInterface $em */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var array $headers An array that follows the csv format*/
    private $headers;

    /** @var string $filecontent*/
    private $filecontent;

    /**
     * ExportService constructor.
     * @param EntityManagerInterface $entityManager
     * @param ContainerInterface $container
     */
    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;
    }

    /**
     * @param array $headers This array should follow the csv format
     * @return ExportService
     */
    public function setHeaders(array $headers) {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Generate file
     * @param  Spreadsheet $spreadsheet 
     * @param  string      $name        
     * @param  string      $type        
     * @return $filename                   
     */
    public function generateFile(Spreadsheet $spreadsheet, string $name, string $type)
    {
        // step 3 : scaning sheet into csv or excel
        if($type == "csv"){
            $writer = IOFactory::createWriter($spreadsheet, 'Csv');
            $writer->setEnclosure('');
            $writer->setDelimiter(',');
            $writer->setUseBOM(true);
            $filename = $name.'.csv';
        }
        elseif($type == "xls"){
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $filename = $name.'.xls';
        }
        elseif($type == "ods"){
            $writer = IOFactory::createWriter($spreadsheet, 'Ods');
            $filename = $name.'.ods';
        }
        else{
            return "An error occured with the type file";
        }
        
        $writer->save($filename);
        return $filename;
    }
    
    /**
     * Export data to file (csv, xls or ods)
     * @param  $exportableTable
     * @param  string $name           
     * @param  string $type           
     * @return $filename                 
     */
    public function export($exportableTable, string $name, string $type) {
        $rows = [];

        // step 1 : Convert the mapping as data
        foreach ($exportableTable as $value) {
            if(is_object($value)) {
                if( $value instanceof ExportableInterface) {
                    array_push($rows, $value->getMappedValueForExport());
                }
            } else if(is_array($value)) {
                array_push($rows, $value);
            } else {
                throw new \Exception("The table to export contains a not allowed content ($value). Allowed content: array, ".ExportableInterface::class."");
            }
        }

        // step 2 : sheet construction
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(0);
        $worksheet = $spreadsheet->getActiveSheet();

        if(count($rows) === 0) {
            throw new \Exception("No data to export", Response::HTTP_NO_CONTENT);
        }

        $rowIndex = 1;

        // get table headers titles
        reset($rows);
        $tableHeaders = array_keys($rows[0]);

        foreach ($tableHeaders as $key => $value) {
            $index = chr(ord('A')+ $key).$rowIndex;
            $worksheet->setCellValue($index, $value);
        }

        $rowIndex++;

        foreach ($rows as $key => $value) {

           foreach ($tableHeaders as $colIndex => $header) {
               $index = chr(ord('A')+ $colIndex ).$rowIndex;
               $worksheet->setCellValue($index, $value[$header]);
           }
           $rowIndex++;
        }
        
        try {
            $filename = $this->generateFile($spreadsheet, $name, $type);
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
        
        return $filename;
    }


}
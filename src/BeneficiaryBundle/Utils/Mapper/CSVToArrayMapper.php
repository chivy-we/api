<?php

namespace BeneficiaryBundle\Utils\Mapper;

use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\VulnerabilityCriterion;
use CommonBundle\Entity\Adm1;
use CommonBundle\Entity\Adm2;
use CommonBundle\Entity\Adm3;
use CommonBundle\Entity\Adm4;
use BeneficiaryBundle\Entity\Camp;
use BeneficiaryBundle\Entity\Referral;

class CSVToArrayMapper extends AbstractMapper
{
    /**
     * Get the list of households with their beneficiaries.
     *
     * @param array $sheetArray
     * @param $countryIso3
     *
     * @return array
     *
     * @throws \Exception
     */
    public function fromCSVToArray(array $sheetArray, $countryIso3)
    {
        // Get the mapping for the current country
        $mappingCSV = $this->loadMappingCSVOfCountry($countryIso3);
        $listHouseholdArray = [];
        $householdArray = null;
        $rowHeader = [];
        $formattedHouseholdArray = null;

        foreach ($sheetArray as $indexRow => $row) {
            // Check if no column has been deleted
            if (!$row['A'] && !$row['B'] && !$row['C'] && !$row['D'] && !$row['E'] && !$row['F'] && !$row['G'] && !$row['H'] && !$row['I'] && !$row['J'] && !$row['K'] && !$row['L'] && !$row['M'] && !$row['N'] && !$row['O'] && !$row['P'] && !$row['Q'] && !$row['R'] && !$row['S'] && !$row['T'] && !$row['U'] && !$row['V'] && !$row['W'] && !$row['X'] && !$row['Y'] && !$row['Z'] && !$row['AA'] && !$row['AB'] && !$row['AC'] && !$row['AD'] && !$row['AE']) {
                continue;
            }

            // Index == 1
            if (Household::indexRowHeader === $indexRow) {
                $rowHeader = $row;
            }
            // Index < first row of data
            if ($indexRow < Household::firstRow) {
                continue;
            }

            // Load the household array for the current row
            try {
                $formattedHouseholdArray = $this->mappingCSV($mappingCSV, $countryIso3, $indexRow, $row, $rowHeader);
            } catch (\Exception $exception) {
                throw $exception;
            }
            // Check if it's a new household or just a new beneficiary in the current household
            // If address_street exists it's a new household
            if (array_key_exists('household_locations', $formattedHouseholdArray)) {
                // If there is already a previous household, add it to the list of households and create a new one
                if (null !== $householdArray) {
                    $listHouseholdArray[] = $householdArray;
                }
                $householdArray = $formattedHouseholdArray;
                $householdArray['beneficiaries'] = [$formattedHouseholdArray['beneficiaries']];
            } else {
                // Add beneficiary to existing household
                $householdArray['beneficiaries'][] = $formattedHouseholdArray['beneficiaries'];
            }
        }
        // Add the last household to the list
        if (null !== $formattedHouseholdArray) {
            $listHouseholdArray[] = $householdArray;
        }

        return $listHouseholdArray;
    }

    /**
     * Transform the array from the CSV (with index 'A', 'B') to a formatted array which can be compatible with the
     * function save of a household (with correct index names and correct deep array).
     *
     * @param array $mappingCSV
     * @param $countryIso3
     * @param int   $lineNumber
     * @param array $row
     * @param array $rowHeader
     *
     * @return array
     *
     * @throws \Exception
     */
    private function mappingCSV(array $mappingCSV, $countryIso3, int $lineNumber, array $row, array $rowHeader)
    {
        $formattedHouseholdArray = [];

        foreach ($mappingCSV as $formattedIndex => $csvIndex) {
            if (is_array($csvIndex)) {
                foreach ($csvIndex as $formattedIndex2 => $csvIndex2) {

                    // Retrieve the beneficiary's information from the array
                    $enGivenName = $row[$mappingCSV['beneficiaries']['en_given_name']];
                    $enFamilyName = $row[$mappingCSV['beneficiaries']['en_family_name']];
                    $localGivenName = $row[$mappingCSV['beneficiaries']['local_given_name']];
                    $localFamilyName = $row[$mappingCSV['beneficiaries']['local_family_name']];
                    $gender = $row[$mappingCSV['beneficiaries']['gender']];
                    $dateOfBirth = $row[$mappingCSV['beneficiaries']['date_of_birth']];
                    $status = $row[$mappingCSV['beneficiaries']['status']];
                    $residencyStatus = $row[$mappingCSV['beneficiaries']['residency_status']];

                    // Verify that there are no missing information in each beneficiary
                    if ($localGivenName == null) {
                        throw new \Exception('There is missing/incorrect information at the column '.$mappingCSV['beneficiaries']['local_given_name'].' at the line '.$lineNumber);
                    } elseif ($localFamilyName == null) {
                        throw new \Exception('There is missing/incorrect information at the column '.$mappingCSV['beneficiaries']['local_family_name'].' at the line '.$lineNumber);
                    } elseif (strcasecmp(trim($gender), 'Female') !== 0 && strcasecmp(trim($gender), 'Male') !== 0 &&
                        strcasecmp(trim($gender), 'F') !== 0 && strcasecmp(trim($gender), 'M') !== 0) {
                        throw new \Exception('There is missing/incorrect information at the column '.$mappingCSV['beneficiaries']['gender'].' at the line '.$lineNumber);
                    } elseif (($status !== 'true' && $status !== 'false')) {
                        throw new \Exception('There is missing/incorrect information at the column '.$mappingCSV['beneficiaries']['status'].' at the line '.$lineNumber);
                    } elseif ($dateOfBirth == null) {
                        throw new \Exception('There is missing/incorrect information at the column '.$mappingCSV['beneficiaries']['date_of_birth'].' at the line '.$lineNumber);
                    } elseif ($residencyStatus == null) {
                        throw new \Exception('There is missing/incorrect information at the column '.$mappingCSV['beneficiaries']['residency_status'].' at the line '.$lineNumber);
                    }

                    // Check that residencyStatus has one of the authorized values
                    $authorizedResidencyStatus = ['refugee', 'IDP', 'resident'];
                    // Add case insensitivity
                    $statusIsAuthorized = false;
                    foreach ($authorizedResidencyStatus as $status) {
                        if (strcasecmp($status, $residencyStatus)) {
                            $residencyStatus = $status;
                            $statusIsAuthorized = true;
                        }
                    }
                    if (!$statusIsAuthorized) {
                        throw new \Exception('Your residency status must be either refugee, IDP or resident');
                    }

                    // Check that the year of birth is between 1900 and today
                    if (strrpos($dateOfBirth, '-') !== false) {
                        $yearOfBirth = intval(explode('-', $dateOfBirth)[2]);
                    } elseif (strrpos($dateOfBirth, '/') !== false) {
                        $yearOfBirth = intval(explode('/', $dateOfBirth)[2]);
                    } else {
                        throw new \Exception('The date is not properly formatted in dd-mm-YYYY format');
                    }
                    if ($yearOfBirth < 1900 || $yearOfBirth > intval(date('Y'))) {
                        throw new \Exception('Your year of birth can not be before 1900 or after the current year');
                    }
                    if (null !== $row[$csvIndex2]) {
                        $row[$csvIndex2] = strval($row[$csvIndex2]);
                    }

                    $formattedHouseholdArray[$formattedIndex][$formattedIndex2] = $row[$csvIndex2];
                }
            } else {
                if (null !== $row[$csvIndex]) {
                    $row[$csvIndex] = strval($row[$csvIndex]);
                }

                $formattedHouseholdArray[$formattedIndex] = $row[$csvIndex];
            }
        }
        // Add the country iso3 from the request
        $formattedHouseholdArray['location']['country_iso3'] = $countryIso3;

        if ($formattedHouseholdArray['income_level'] && !in_array($formattedHouseholdArray['income_level'], [1,2,3,4,5])) {
            throw new \Exception('The income level must be between 1 and 5');
        }

        $this->mapLocation($formattedHouseholdArray);

        if ($formattedHouseholdArray['camp']) {
            if (!$formattedHouseholdArray['tent_number']) {
                throw new \Exception('You have to enter a tent number');
            }
            $campName = $formattedHouseholdArray['camp'];
            $formattedHouseholdArray['household_locations'] = [
                [
                    'location_group' => 'current',
                    'type' => 'camp',
                    'camp_address' => [
                        'camp' => [
                            'id' => null,
                            'name' => $campName,
                            'location' => $formattedHouseholdArray['location']
                        ],
                        'tent_number' =>  $formattedHouseholdArray['tent_number'],
                    ]
                ]
            ];
            $alreadyExistingCamp = $this->em->getRepository(Camp::class)->findOneBy(['name' => $campName]);
            if ($alreadyExistingCamp) {
                $formattedHouseholdArray['household_locations'][0]['camp_address']['camp']['id'] = $alreadyExistingCamp->getId();
            }
        } else if ($formattedHouseholdArray['address_number']) {
            if (!$formattedHouseholdArray['address_street'] || !$formattedHouseholdArray['address_postcode']) {
                throw new \Exception('The address is invalid');
            }
            $formattedHouseholdArray['household_locations'] = [
                [
                    'location_group' => 'current',
                    'type' => 'residence',
                    'address' => [
                        'number' => $formattedHouseholdArray['address_number'],
                        'street' =>  $formattedHouseholdArray['address_street'],
                        'postcode' =>  $formattedHouseholdArray['address_postcode'],
                        'location' => $formattedHouseholdArray['location']
                    ]
                ]
            ];
        }

        unset($formattedHouseholdArray['location']);
        unset($formattedHouseholdArray['address_number']);
        unset($formattedHouseholdArray['address_street']);
        unset($formattedHouseholdArray['address_postcode']);
        unset($formattedHouseholdArray['camp']);
        unset($formattedHouseholdArray['tent_number']);

        // Treatment on field with multiple value or foreign key inside (switch name to id for example)
        try {
            $this->mapCountrySpecifics($mappingCSV, $formattedHouseholdArray, $rowHeader);
            $this->mapVulnerabilityCriteria($formattedHouseholdArray);
            $this->mapPhones($formattedHouseholdArray);
            $this->mapGender($formattedHouseholdArray);
            $this->mapNationalIds($formattedHouseholdArray);
            $this->mapProfile($formattedHouseholdArray);
            $this->mapStatus($formattedHouseholdArray);
            $this->mapReferral($formattedHouseholdArray);
            $this->mapLivelihood($formattedHouseholdArray);
        } catch (\Exception $exception) {
            throw $exception;
        }
        // ADD THE FIELD COUNTRY ONLY FOR THE CHECKING BY THE REQUEST VALIDATOR
        $formattedHouseholdArray['__country'] = $countryIso3;

        return $formattedHouseholdArray;
    }

    /**
     * Reformat the fields countries_specific_answers.
     *
     * @param array $mappingCSV
     * @param $formattedHouseholdArray
     * @param array $rowHeader
     */
    private function mapCountrySpecifics(array $mappingCSV, &$formattedHouseholdArray, array $rowHeader)
    {
        $formattedHouseholdArray['country_specific_answers'] = [];
        foreach ($formattedHouseholdArray as $indexFormatted => $value) {
            if (substr($indexFormatted, 0, 20) === 'tmp_country_specific') {
                $field = $rowHeader[$mappingCSV[$indexFormatted]];
                $countrySpecific = $this->em->getRepository(CountrySpecific::class)
                    ->findOneByFieldString($field);
                $formattedHouseholdArray['country_specific_answers'][] = [
                    'answer' => $value,
                    'country_specific' => ['id' => $countrySpecific->getId()],
                ];
                unset($formattedHouseholdArray[$indexFormatted]);
            }
        }
    }

    /**
     * Reformat the field which contains vulnerability criteria => switch list of names to a list of ids.
     *
     * @param $formattedHouseholdArray
     */
    private function mapVulnerabilityCriteria(&$formattedHouseholdArray)
    {
        $vulnerability_criteria_string = $formattedHouseholdArray['beneficiaries']['vulnerability_criteria'];
        $vulnerability_criteria_array = array_map('trim', explode(';', $vulnerability_criteria_string));
        $formattedHouseholdArray['beneficiaries']['vulnerability_criteria'] = [];
        foreach ($vulnerability_criteria_array as $item) {
            $vulnerability_criterion = $this->em->getRepository(VulnerabilityCriterion::class)->findOneByFieldString($item);
            if (!$vulnerability_criterion instanceof VulnerabilityCriterion) {
                continue;
            }
            $formattedHouseholdArray['beneficiaries']['vulnerability_criteria'][] = ['id' => $vulnerability_criterion->getId()];
        }
    }

    /**
     * Reformat the field phones => switch string 'type-number' to [type => type, number => number].
     *
     * @param $formattedHouseholdArray
     */
    private function mapPhones(&$formattedHouseholdArray)
    {
        $types1_string = $formattedHouseholdArray['beneficiaries']['phone1_type'];
        $phone1_prefix_string = $formattedHouseholdArray['beneficiaries']['phone1_prefix'];
        $phone1_number_string = $formattedHouseholdArray['beneficiaries']['phone1_number'];
        $phone1_proxy_string = $formattedHouseholdArray['beneficiaries']['phone1_proxy'];

        $phone1_prefix_string = str_replace("'", '', $phone1_prefix_string);
        $phone1_number_string = str_replace("'", '', $phone1_number_string);

        $formattedHouseholdArray['beneficiaries']['phones'] = [];
        array_push($formattedHouseholdArray['beneficiaries']['phones'], array('type' => $types1_string, 'prefix' => $phone1_prefix_string, 'number' => $phone1_number_string, 'proxy' => $phone1_proxy_string));

        if (key_exists('phone2_type', $formattedHouseholdArray['beneficiaries'])) {
            $phone2_type_string = $formattedHouseholdArray['beneficiaries']['phone2_type'];
            $phone2_prefix_string = $formattedHouseholdArray['beneficiaries']['phone2_prefix'];
            $phone2_number_string = $formattedHouseholdArray['beneficiaries']['phone2_number'];
            $phone2_proxy_string = $formattedHouseholdArray['beneficiaries']['phone2_proxy'];

            $phone2_prefix_string = str_replace("'", '', $phone2_prefix_string);
            $phone2_number_string = str_replace("'", '', $phone2_number_string);

            array_push($formattedHouseholdArray['beneficiaries']['phones'], ['type' => $phone2_type_string, 'prefix' => $phone2_prefix_string, 'number' => $phone2_number_string, 'proxy' => $phone2_proxy_string]);
        }
    }
    
    /**
     * Reformat the field gender
     *
     * @param $formattedHouseholdArray
     */
    private function mapGender(&$formattedHouseholdArray)
    {
        $gender_string = trim($formattedHouseholdArray['beneficiaries']['gender']);

        if (strcasecmp(trim($gender_string), 'Male') === 0 || strcasecmp(trim($gender_string), 'M') === 0) {
            $formattedHouseholdArray['beneficiaries']['gender'] = 1;
        } else if (strcasecmp(trim($gender_string), 'Female') === 0 || strcasecmp(trim($gender_string), 'F') === 0) {
            $formattedHouseholdArray['beneficiaries']['gender'] = 0;
        }
    }

    /**
     * Reformat the field nationalids => switch string 'idtype-idnumber' to [id_type => idtype, id_number => idnumber].
     *
     * @param $formattedHouseholdArray
     */
    private function mapNationalIds(&$formattedHouseholdArray)
    {
        $type_national_id = $formattedHouseholdArray['beneficiaries']['national_id_type'];
        $national_id_string = $formattedHouseholdArray['beneficiaries']['national_id_number'];
        $formattedHouseholdArray['beneficiaries']['national_ids'] = [];
        if ($national_id_string != '') {
            $formattedHouseholdArray['beneficiaries']['national_ids'][] = ['id_type' => $type_national_id, 'id_number' => $national_id_string];
        }
    }

    /**
     * Reformat the field profile.
     *
     * @param $formattedHouseholdArray
     */
    private function mapProfile(&$formattedHouseholdArray)
    {
        $formattedHouseholdArray['beneficiaries']['profile'] = ['photo' => ''];
    }

    /**
     * Reformat the field status.
     *
     * @param $formattedHouseholdArray
     */
    private function mapStatus(&$formattedHouseholdArray)
    {
        $formattedHouseholdArray['beneficiaries']['status'] =  $formattedHouseholdArray['beneficiaries']['status'] === 'false' ? 0 : 1;
    }

    /**
     * Reformat the field location.
     *
     * @param $formattedHouseholdArray
     */
    public function mapLocation(&$formattedHouseholdArray)
    {
        $location = $formattedHouseholdArray['location'];

        if ($location['adm1'] === null && $location['adm2'] === null && $location['adm3'] === null && $location['adm4'] === null) {
            if ($formattedHouseholdArray['address_street'] || $formattedHouseholdArray['camp']) {
                throw new \Exception('A location is required');
            } else {
                return;
            }
        }

        if (! $location['adm1']) {
            throw new \Exception('An Adm1 is required');
        }

        // Map adm1
        $adm1 = $this->em->getRepository(Adm1::class)->findOneBy(
            [
                'name' => $location['adm1'],
                'countryISO3' => $location['country_iso3']
            ]
        );
        
        if (! $adm1 instanceof Adm1) {
            throw new \Exception('The Adm1 ' . $location['adm1'] . ' was not found in ' . $location['country_iso3']);
        } else {
            $formattedHouseholdArray['location']['adm1'] = $adm1->getId();
        }

        if (! $location['adm2']) {
            return;
        }

        // Map adm2
        $adm2 = $this->em->getRepository(Adm2::class)->findOneBy(
            [
                'name' => $location['adm2'],
                'adm1' => $adm1
            ]
        );
        
        if (! $adm2 instanceof Adm2) {
            throw new \Exception('The Adm2 ' . $location['adm2'] . ' was not found in ' . $adm1->getName());
        } else {
            $formattedHouseholdArray['location']['adm2'] = $adm2->getId();
        }

        if (! $location['adm3']) {
            return;
        }

        // Map adm3
        $adm3 = $this->em->getRepository(Adm3::class)->findOneBy(
            [
                'name' => $location['adm3'],
                'adm2' => $adm2
            ]
        );
        
        if (! $adm3 instanceof Adm3) {
            throw new \Exception('The Adm3 ' . $location['adm3'] . ' was not found in ' . $adm2->getName());
        } else {
            $formattedHouseholdArray['location']['adm3'] = $adm3->getId();
        }

        if (! $location['adm4']) {
            return;
        }

        // Map adm4
        $adm4 = $this->em->getRepository(Adm4::class)->findOneBy(
            [
                'name' => $location['adm4'],
                'adm3' => $adm3
            ]
        );
        
        if (! $adm4 instanceof Adm4) {
            throw new \Exception('The Adm4 ' . $location['adm4'] . ' was not found in ' . $adm3->getName());
        } else {
            $formattedHouseholdArray['location']['adm4'] = $adm4->getId();
        }
    }

    public function mapReferral(&$formattedHouseholdArray)
    {
        if ($formattedHouseholdArray['beneficiaries']['referral_type']) {
            $referralType = null;
            foreach (Referral::REFERRALTYPES as $referralTypeId => $value) {
                if (strcasecmp($value, $formattedHouseholdArray['beneficiaries']['referral_type']) === 0) {
                    $referralType = $referralTypeId;
                }
            }
            if ($referralType !== null) {
                $formattedHouseholdArray['beneficiaries']['referral_type'] = $referralType;
            } else {
                throw new \Exception("Invalid referral type.");
            }
        }
    }

    /**
     * Reformat the field livelihood.
     *
     * @param $formattedHouseholdArray
     */
    public function mapLivelihood(&$formattedHouseholdArray)
    {
        if ($formattedHouseholdArray['livelihood']) {
            $livelihood = null;
            foreach (Household::LIVELIHOOD as $livelihoodId => $value) {
                if (strcasecmp($value, $formattedHouseholdArray['livelihood']) === 0) {
                    $livelihood = $livelihoodId;
                }
            }
            if ($livelihood !== null) {
                $formattedHouseholdArray['livelihood'] = $livelihood;
            } else {
                throw new \Exception("Invalid livelihood.");
            }
        }
    }
}

<?php


namespace CommonBundle\DataFixtures;

use DistributionBundle\Utils\DistributionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class DistributionFixtures extends Fixture
{

    private $distributionArray = [
        'adm1' => '',
        'adm2' => '',
        'adm3' => '',
        'adm4' => '',
        'commodities' => [
            0 => [
                'modality' => 'Food',
                'modality_type' => [
                    'id' => 2,
                ],
                'type' => 'Banana',
                'unit' => '12',
                'value' => '45'
            ]
        ],
        'date_distribution' => '2018-09-13',
        'location' => [
            'adm1' => 'Battambang',
            'adm2' => 'Bavel',
            'adm3' => '',
            'adm4' => '',
            'country_iso3' => 'KHM'
        ],
        'location_name' => '',
        'name' => 'Dev Project-Battambang-9/13/2018-',
        'project' => [
            'donors' => [],
            'donors_name' => [],
            'id' => '1',
            'name' => '',
            'sectors' => [],
            'sectors_name' => [],
        ],
        'selection_criteria' => [
            0 => [
                'condition_string' => 'true',
                'field_string' => 'disabled',
                'id_field' => 1,
                'kind_beneficiary' => 'Beneficiary',
                'table_string' => 'vulnerabilityCriteria'
            ]
        ],
        'type' => 'Beneficiary'
    ];

    private $distributionService;

    public function __construct(DistributionService $distributionService)
    {
        $this->distributionService = $distributionService;
    }


    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $this->distributionService->create("KHM", $this->distributionArray);
    }
}
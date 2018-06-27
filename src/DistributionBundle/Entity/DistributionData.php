<?php

namespace DistributionBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Query\Expr\Select;
use ProjectBundle\Entity\Project;
use JMS\Serializer\Annotation\Type as JMS_Type;

/**
 * DistributionData
 *
 * @ORM\Table(name="distribution_data")
 * @ORM\Entity(repositoryClass="DistributionBundle\Repository\DistributionDataRepository")
 */
class DistributionData
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=45)
     */
    private $name;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="UpdatedOn", type="datetime")
     * @JMS_Type("DateTime<'Y-m-d H:m:i'>")
     */
    private $updatedOn;

    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="DistributionBundle\Entity\Location")
     */
    private $location;

    /**
     * @var Project
     *
     * @ORM\ManyToOne(targetEntity="ProjectBundle\Entity\Project")
     */
    private $project;

    /**
     * @var SelectionCriteria
     *
     * @ORM\ManyToOne(targetEntity="DistributionBundle\Entity\SelectionCriteria")
     */
    private $selectionCriteria;

    /**
     * @var boolean
     *
     * @ORM\Column(name="archived", type="boolean", options={"default" : 0})
     */
    private $archived = 0;
    

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return DistributionData
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set archived.
     *
     * @param bool $archived
     *
     * @return DistributionData
     */
    public function setArchived($archived)
    {
        $this->archived = $archived;

        return $this;
    }

    /**
     * Get archived.
     *
     * @return bool
     */
    public function getArchived()
    {
        return $this->archived;
    }

    /**
     * Set location.
     *
     * @param \DistributionBundle\Entity\Location|null $location
     *
     * @return DistributionData
     */
    public function setLocation(\DistributionBundle\Entity\Location $location = null)
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Get location.
     *
     * @return \DistributionBundle\Entity\Location|null
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set project.
     *
     * @param \ProjectBundle\Entity\Project|null $project
     *
     * @return DistributionData
     */
    public function setProject(\ProjectBundle\Entity\Project $project = null)
    {
        $this->project = $project;

        return $this;
    }

    /**
     * Get project.
     *
     * @return \ProjectBundle\Entity\Project|null
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * Set selectionCriteria.
     *
     * @param \DistributionBundle\Entity\SelectionCriteria|null $selectionCriteria
     *
     * @return DistributionData
     */
    public function setSelectionCriteria(\DistributionBundle\Entity\SelectionCriteria $selectionCriteria = null)
    {
        $this->selectionCriteria = $selectionCriteria;

        return $this;
    }

    /**
     * Get selectionCriteria.
     *
     * @return \DistributionBundle\Entity\SelectionCriteria|null
     */
    public function getSelectionCriteria()
    {
        return $this->selectionCriteria;
    }

    /**
     * Set updatedOn.
     *
     * @param \DateTime $updatedOn
     *
     * @return DistributionData
     */
    public function setUpdatedOn($updatedOn)
    {
        $this->updatedOn = $updatedOn;
        dump($updatedOn);
        dump($this->updatedOn);
        return $this;
    }

    /**
     * Get updatedOn.
     *
     * @return \DateTime
     */
    public function getUpdatedOn()
    {
        return $this->updatedOn;
    }
}

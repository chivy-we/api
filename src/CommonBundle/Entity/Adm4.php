<?php

namespace CommonBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

/**
 * Adm4
 *
 * @see Adm1 For a better understanding of Adm
 *
 * @ORM\Table(name="adm4")
 * @ORM\Entity(repositoryClass="CommonBundle\Repository\Adm4Repository")
 */
class Adm4
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"FullHousehold", "SmallHousehold", "FullDistribution", "FullVendor"})
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     * @Groups({"FullHousehold", "SmallHousehold", "FullDistribution", "FullVendor"})
     */
    private $name;

    /**
     * @var Adm3
     *
     * @ORM\ManyToOne(targetEntity="CommonBundle\Entity\Adm3")
     * @Groups({"FullHousehold", "SmallHousehold", "FullDistribution", "FullVendor"})
     */
    private $adm3;

    /**
     * @var Location
     *
     * @ORM\OneToOne(targetEntity="CommonBundle\Entity\Location", inversedBy="adm4", cascade={"persist"})
     */
    private $location;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=255, nullable=true)
     * @Groups({"FullHousehold", "SmallHousehold", "FullDistribution", "FullVendor"})
     */
    private $code;


    public function __construct()
    {
        $this->setLocation(new Location());
    }


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
     * @return Adm4
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
     * Set adm3.
     *
     * @param \CommonBundle\Entity\Adm3|null $adm3
     *
     * @return Adm4
     */
    public function setAdm3(\CommonBundle\Entity\Adm3 $adm3 = null)
    {
        $this->adm3 = $adm3;

        return $this;
    }

    /**
     * Get adm3.
     *
     * @return \CommonBundle\Entity\Adm3|null
     */
    public function getAdm3()
    {
        return $this->adm3;
    }

    /**
     * Set location.
     *
     * @param \CommonBundle\Entity\Location|null $location
     *
     * @return Adm4
     */
    public function setLocation(\CommonBundle\Entity\Location $location = null)
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Get location.
     *
     * @return \CommonBundle\Entity\Location|null
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set code.
     *
     * @param string $code
     *
     * @return Adm4
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }
}

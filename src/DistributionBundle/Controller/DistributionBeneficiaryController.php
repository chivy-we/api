<?php

namespace DistributionBundle\Controller;

use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;
use DistributionBundle\Entity\DistributionBeneficiary;


use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;

class DistributionBeneficiaryController extends Controller
{
      /**
     * Create a distributionBeneficiary
     * @Rest\Put("/distribution/beneficiaries", name="add_distribution_benefeciaries")
     * 
     * @SWG\Tag(name="DistributionBeneficiaries")
     * 
          * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="object",
     *      required=true,
     *      description="Body of the request",
     * 	  @SWG\Schema(ref=@Model(type=DistributionBeneficiary::class))
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Distribution Beneficiary created",
     *     @Model(type=DistributionBeneficiary::class)
     * )
     * 
     * @param Request $request
     * @return Response
     */
    public function addAction(Request $request)
    {
        $distributionBeneficiaryArray = $request->request->all();

        try
        {
            $distribution = $this->get('distribution.distribution_beneficiary_service')->create($distributionBeneficiaryArray);
        }
        catch (\Exception $exception)
        {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $json = $this->get('jms_serializer')->serialize($distribution, 'json', SerializationContext::create()->setSerializeNull(true));

        return new Response($json);
    }

    /**
     * @Rest\Get("/distribution/beneficiaries", name="get_all_distribution_benefeciaries")
     *
     * @SWG\Tag(name="DistributionBeneficiaries")
     *
     * @SWG\Response(
     *     response=200,
     *     description="All distribution beneficiaries",
     *     @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref=@Model(type=DistributionBeneficiary::class))
     *     )
     * )
     * 
     * @param Request $request
     * @return Response
     */
    public function getAllAction(Request $request)
    {
        $distributionBeneficiary = $this->get('distribution.distribution_beneficiary_service')->findAll();
        $json = $this->get('jms_serializer')->serialize($distributionBeneficiary, 'json');

        return new Response($json);
    }

    /**
     * Delete a distribution beneficiary
     * @Rest\Delete("/distribution/beneficiaries/delete/{id}", name="delete_distribution_benefeciaries")
     *
     * @SWG\Tag(name="DistributionBeneficiaries")
     *
     * @SWG\Response(
     *     response=200,
     *     description="OK"
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param DistributionBeneficiary $distributionBeneficiary
     * @return Response
     */
    public function deleteAction(DistributionBeneficiary $distributionBeneficiary)
    {
        try
        {
            $valid = $this->get('distribution.distribution_beneficiary_service')->delete($distributionBeneficiary);
        }
        catch (\Exception $e)
        {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($valid)
            return new Response("", Response::HTTP_OK);
        if (!$valid)
            return new Response("", Response::HTTP_BAD_REQUEST);
    }
}
<?php

namespace CommonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use CommonBundle\Entity\Organization;
use JMS\Serializer\SerializationContext;

/**
 * Class OrganizationController
 * @package OrganizationBundle\Controller
 */
class OrganizationController extends Controller
{

    /**
     * @Rest\Get("/organization", name="get_organization")
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * @SWG\Tag(name="Organization")
     *
     * @SWG\Response(
     *     response=200,
     *     description="OK"
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="HTTP_BAD_REQUEST"
     * )
     * @param Request $request
     * @return Response
     */
    public function getOrganizationAction(Request $request)
    {        
        try {
            $organization = $this->get('organization_service')->get();
           
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        
        $json = $this->get('jms_serializer')->serialize($organization, 'json', null);
        
        return new Response($json);
    }

    /**
     * @Rest\Post("/organization/{id}", name="update_organization")
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * @SWG\Tag(name="Organization")
     *
     * @SWG\Parameter(
     *     name="organization",
     *     in="body",
     *     required=true,
     *     @Model(type=Organization::class, groups={"FullOrganization"})
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Organization updated",
     *     @Model(type=Organization::class)
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Request $request
     * @param Organization $organization
     * @return Response
     */
    public function updateAction(Request $request, Organization $organization)
    {
        $organizationArray = $request->request->all();

        try {
            $organization = $this->get('organization_service')->edit($organization, $organizationArray);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $organizationJson = $this->get('jms_serializer')
            ->serialize($organization, 'json', SerializationContext::create()->setGroups(['FullOrganization'])->setSerializeNull(true));

        return new Response($organizationJson);
    }

     /**
     * @Rest\Post("/organization/upload/logo", name="upload_logo")
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * @SWG\Tag(name="Organization")
     *
     * @SWG\Parameter(
     *     name="file",
     *     in="formData",
     *     required=true,
     *     type="file"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Image uploaded",
     *     @SWG\Schema(
     *          type="string"
     *     )
     * )
     *
     * @param Request $request
     * @return Response
     */
    public function uploadLogoAction(Request $request)
    {
        $content = $request->getContent();
        $file = $request->files->get('file');

        $type = $file->getMimeType();
        if ($type !== 'image/gif' && $type !== 'image/jpeg' && $type !== 'image/png') {
            return new Response('The image type must be gif, png or jpg.', Response::HTTP_BAD_REQUEST);
        }

        $adapter = $this->container->get('knp_gaufrette.filesystem_map')->get('organization')->getAdapter();
        $filename = $this->get('common.upload_service')->uploadImage($file, $adapter);
        $bucketName = $this->getParameter('aws_s3_bucket_name');
        $region = $this->getParameter('aws_s3_region');

        $return = 'https://s3.'.$region.'.amazonaws.com/'.$bucketName.'/organization/'.$filename;
        return new Response(json_encode($return));
    }

    /**
     * To print a template of the organization pdf
     *
     * @Rest\Get("/organization/print/template", name="print_template")
     * @Security("is_granted('ROLE_ADMIN')")
     * 
     * @SWG\Tag(name="Organization")
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @return Response
     */
    public function printTemplateAction()
    {
        try {
            return $this->get('organization_service')->printTemplate();
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}

<?php

namespace ProjectBundle\Controller;

use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ProjectBundle\Entity\Project;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class ProjectController extends Controller
{
    /**
     * Get projects
     * @Rest\Get("/projects", name="get_all_projects")
     * @Security("is_granted('ROLE_PROJECT_MANAGEMENT_READ')")
     *
     * @SWG\Tag(name="Projects")
     *
     * @SWG\Response(
     *     response=200,
     *     description="All Projects",
     *     @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref=@Model(type=Project::class))
     *     )
     * )
     *
     * @return Response
     */
    public function getAllAction(Request $request)
    {
        $user = $this->getUser();
        $projects = $this->get('project.project_service')->findAll($request->request->get('__country'), $user);
        $json = $this->get('jms_serializer')
            ->serialize($projects, 'json', SerializationContext::create()->setGroups(['FullProject'])->setSerializeNull(true));

        return new Response($json, Response::HTTP_OK);
    }

    /**
     * Get a project
     * @Rest\Get("/projects/{id}", name="show_project")
     * @Security("is_granted('ROLE_PROJECT_MANAGEMENT_READ', project)")
     *
     * @SWG\Tag(name="Projects")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Project asked",
     *     @Model(type=Project::class)
     * )
     *
     * @return Response
     */
    public function showAction(Project $project)
    {
        $json = $this->get('jms_serializer')
            ->serialize($project, 'json', SerializationContext::create()->setGroups(['FullProject'])->setSerializeNull(true));

        return new Response($json, Response::HTTP_OK);
    }

    /**
     * Create a project
     * @Rest\Put("/projects", name="add_project")
     * @Security("is_granted('ROLE_PROJECT_MANAGEMENT_WRITE')")
     *
     * @SWG\Tag(name="Projects")
     *
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="object",
     *      required=true,
     *      description="Body of the request",
     * 	  @SWG\Schema(ref=@Model(type=Project::class))
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Project created",
     *     @Model(type=Project::class)
     * )
     *
     * @param Request $request
     * @return Response
     */
    public function addAction(Request $request)
    {
        $projectArray = $request->request->all();
        $country = $projectArray['__country'];
        unset($projectArray['__country']);
        $user = $this->getUser();

        try
        {
            $project = $this->get('project.project_service')->create($country, $projectArray, $user);
        }
        catch (\Exception $e)
        {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        $json = $this->get('jms_serializer')
            ->serialize($project, 'json', SerializationContext::create()->setGroups(['FullProject'])->setSerializeNull(true));
        return new Response($json, Response::HTTP_OK);
    }

    /**
     * TODO VOTER POUR CHECKER QUE PROJECT EST PAS ARCHIVED
     * Edit a project
     * @Rest\Post("/projects/{id}", name="update_project")
     * @Security("is_granted('ROLE_PROJECT_MANAGEMENT_WRITE', project)")
     *
     * @SWG\Tag(name="Projects")
     *
     * @SWG\Parameter(
     *     name="Project",
     *     in="body",
     *     required=true,
     *     @Model(type=Project::class, groups={"FullProject"})
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Project updated",
     *     @Model(type=Project::class)
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Request $request
     * @param Project $project
     * @return Response
     */
    public function updateAction(Request $request, Project $project)
    {
        $projectArray = $request->request->all();
        try
        {
            $project = $this->get('project.project_service')->edit($project, $projectArray);
        }
        catch (\Exception $e)
        {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        $json = $this->get('jms_serializer')
            ->serialize($project, 'json', SerializationContext::create()->setGroups(['FullProject'])->setSerializeNull(true));
        return new Response($json, Response::HTTP_OK);
    }

    /**
     * Edit a project
     * @Rest\Delete("/projects/{id}", name="delete_project")
     * @Security("is_granted('ROLE_PROJECT_MANAGEMENT_WRITE', project)")
     *
     * @SWG\Tag(name="Projects")
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
     * @param Project $project
     * @return Response
     */
    public function deleteAction(Project $project)
    {
        try
        {
            $valid = $this->get('project.project_service')->delete($project);
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


    /**
     * @Rest\Get("/project/export", name="project_export")
     * TODO: ADd security on project
     * @ Security("is_granted('ROLE_PROJECT_MANAGEMENT_READ', project)")
     *
     * @SWG\Tag(name="Project")
     *
     * @SWG\Response(
     *     response=200,
     *     description="OK"
     * )
     *
     * @SWG\Response(
     *     response=204,
     *     description="HTTP_NO_CONTENT"
     * )
     * @return Response
     */

    public function exportToCSVAction() {

        try{
            $fileCSV = $this->get('project.project_service')->exportToCsv();
            return new Response(json_encode($fileCSV));
        } catch(\Exception $exception) {
            return new Response($exception->getMessage(), $exception->getCode() >= 200 ? $exception->getCode() : Response::HTTP_BAD_REQUEST);
        }
    }


}

<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace SimpleThings\EntityAudit\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use FOS\UserBundle\Model\UserInterface;

/**
 * Controller for listing auditing information
 *
 * @author Tim Nagel <tim@nagel.com.au>
 */
class AuditController extends ContainerAware
{
    /**
     * @return \SimpleThings\EntityAudit\AuditReader
     */
    protected function getAuditReader()
    {
        return $this->container->get('simplethings_entityaudit.reader');
    }

    /**
     * @return \SimpleThings\EntityAudit\AuditManager
     */
    protected function getAuditManager()
    {
        return $this->container->get('simplethings_entityaudit.manager');
    }

    /**
     * Renders a paginated list of revisions.
     *
     * @param int $page
     * @return Response
     */
    public function indexAction($page = 1)
    {
        $reader = $this->getAuditReader();
        $revisions = $reader->findRevisionHistory(20, 20 * ($page - 1));

        return $this->container->get('templating')->renderResponse('SimpleThingsEntityAuditBundle:Audit:index.html.twig', array(
            'revisions' => $revisions,
        ));
    }

    public function viewRevisionAction($rev)
    {
        $revision = $this->getAuditReader()->findRevision($rev);
        if (!$revision) {
            throw new NotFoundHttpException(sprintf('Revision %i not found', $rev));
        }

        $changedEntities = $this->getAuditReader()->findEntitesChangedAtRevision($rev);

        return $this->container->get('templating')->renderResponse('SimpleThingsEntityAuditBundle:Audit:view_revision.html.twig', array(
            'revision' => $revision,
            'changedEntities' => $changedEntities,
        ));
    }

    public function viewEntityAction($className, $id)
    {
        $ids = explode(',', $id);
        $revisions = $this->getAuditReader()->findRevisions($className, $ids);

        return $this->container->get('templating')->renderResponse('SimpleThingsEntityAuditBundle:Audit:view_entity.html.twig', array(
            'id' => $id,
            'className' => $className,
            'revisions' => $revisions,
        ));
    }

    public function viewDetailAction($className, $id, $rev)
    {
        $ids = explode(',', $id);
        $entity = $this->getAuditReader()->find($className, $ids, $rev);

        return $this->container->get('templating')->renderResponse('SimpleThingsEntityAuditBundle:Audit:view_detail.html.twig', array(
            'entity' => $entity,
        ));
    }

    public function compareAction(Request $request, $className, $id, $oldRev = null, $newRev = null)
    {
        $em = $this->container->get('doctrine')->getEntityManagerForClass($className);
        $metadata = $em->getClassMetadata($className);

        if (null === $oldRev) {
            $oldRev = $request->query->get('oldRev');
        }

        if (null === $newRev) {
            $newRev = $request->query->get('newRev');
        }

        $ids = explode(',', $id);
        $oldEntity = $this->getAuditReader()->find($className, $ids, $oldRev);
        $newEntity = $this->getAuditReader()->find($className, $ids, $newRev);

        $fields = $metadata->getFieldNames();
        sort($fields);

        $oldValues =
        $newValues = array();
        foreach ($fields AS $fieldName) {
            $oldValues[$fieldName] = $metadata->getFieldValue($oldEntity, $fieldName);
            $newValues[$fieldName] = $metadata->getFieldValue($newEntity, $fieldName);
        }

        $SimpleDiff = new \SimpleThings\EntityAudit\Utils\SimpleDiff();
        $diff = $SimpleDiff->entityDiff($oldValues, $newValues);

        return $this->container->get('templating')->renderResponse('SimpleThingsEntityAuditBundle:Audit:compare.html.twig', array(
            'className' => $className,
            'id' => $id,
            'oldRev' => $oldRev,
            'newRev' => $newRev,
            'diff' => $diff,
        ));
    }
}
<?php

namespace Falcon\Site\Component\AccessControl;

use Entities\AclRole, Entities\AclResource, Entities\AclRule,
	Symfony\Component\EventDispatcher\Event,
	Symfony\Component\HttpFoundation\Request,
	Falcon\Site\Component\Service\ServiceContainerAware,
	Falcon\Site\Component\AccessControl\Exception\AccessControlException as Exception
;

class AccessControl
	extends ServiceContainerAware
{
	protected static $cacheId = 'access_control';
	protected static $cacheAclId = 'acl';
	
	protected $entityManagerService;
	protected $cacheManagerService;
	protected $acl = null;
	
	protected function getMatchingResource($resource, Request $request = null)
	{
		// if the resource is not empty and is a string
		if(!empty($resource) && (is_string($resource) || $resource instanceof \Entities\AclResource)) {
			$name = is_string($resource) ? $resource : $resource->getResource();
			// if the acl get a matching resource with exactly this name
			if($this->acl->has($name)) {
				return $this->acl->get($name);
			}
			// match without the trailing slash
			elseif($this->acl->has(preg_replace('#/$#', '', $name))) {
				return $this->acl->get(preg_replace('#/$#', '', $name));
			}
			// no exact match, but request
			elseif(!empty($request)) {
				$module		= $request->attributes->get('module');
				$controller	= $request->attributes->get('controller');
				$action		= $request->attributes->get('action');
				// match the module + controller + action
				if(
					!empty($action)
					&& !empty($controller)
					&& !empty($module)
					&& $this->acl->has("$module/$controller/$action")
				) {
					return $this->acl->get("$module/$controller/$action");
				}
				// match the module + controller
				elseif(
					!empty($controller)
					&& !empty($module)
					&& $this->acl->has("$module/$controller")
				) {
					return $this->acl->get("$module/$controller");
				}
				// match the module
				elseif(!empty($module) && $this->acl->has($module)) {
					return $this->acl->get($module);
				}
			}
		}
		return null;
	}

	public function check(AclRole $role, AclResource $resource = null, Request $request = null)
	{
		/*Debug::dump(array(
			'role' => $role->getRole(),
			'resource' => empty($resource) ?: $resource->getResource(),
			'empty request ?' => empty($request),
			'has role' => $this->acl->hasRole($role),
			'has resource' => $this->acl->has($resource),
			'matching resource' => $this->getMatchingResource($resource, $request)
		), true);*/
		if(!$this->acl->hasRole($role)) {
			throw new Exception("Falcon\Site\AccessControl::check : role '$role' doesn't exists");
		}
		if(!$this->acl->has($resource)) {
			$resource = $this->getMatchingResource($resource, $request);
			//throw new Exception("Falcon\Site\AccessControl::check : resource '$resource' doesn't exist");
		}
		return $this->acl->isAllowed($role, $resource, 'use');
	}
	
	/**
	 * Returns the role, else return false
	 * @param string $name The role name
	 * @return Entities\AclRole | false
	 */
	public function getRole($name)
	{
		// if the name is not empty
		if(!empty($name)) {
			$dql = "SELECT r FROM Entities\AclRole r WHERE r.role = ?1 AND r.deleted = false";
			$query = $this->getEntityManagerService()->createQuery($dql);
			$query->setParameters(1, $name);
			try {
				$checkRole = $query->getSingleResult();
				return $checkRole;
			}
			// no role found
			catch(\Doctrine\ORM\NoResultException $e) {}
		}
		return null;
	}
	
	protected function createAndSaveRole($name)
	{
		$role = null;
		// if the role is a string
		if(is_string($name)) {
			// try to get the role (if already exists)
			$role = $this->getRole($name);
			// if role doesn't exists
			if(empty($role)) {
				// create a new one
				$role = new AclRole($name);
				// get entity manager
				$em = $this->getEntityManagerService();
				// save the new role
				$em->persist($role);
				$em->flush();
			}
		}
		return $role;
	}

	public function addRole($role, $parent = null, $autoUpdateCache = true)
	{
		// if the role is not empty and is not already registered in the acl
		if(
			!empty($role)
			&& (!$role instanceof AclRole || !$this->acl->hasRole($role))
		) {
			// if the parent is null and the role is a AclRole
			if(empty($parent) && $role instanceof AclRole) {
				$parent = $role->getParent();
			}
			// if the parent role is a string
			if(is_string($parent)) {
				// create and save the parent role
				$parent = $this->createAndSaveRole($parent);
			}
			// if the parent role is a AclRole and the current acl doesn't have the role
			if($parent instanceof AclRole && !$this->acl->hasRole($parent)) {
				// add role to the current acl
				$this->addRole($parent, null, false);
			}
			// if the role is a string
			if(is_string($role)) {
				// create and save the role
				$role = $this->createAndSaveRole($role);
			}
			// if the role is a AclRole and the current acl doesn't have the role
			if($role instanceof AclRole && !$this->acl->hasRole($role)) {
				// add role to the current acl
				$this->acl->addRole($role, $parent);
				// update the acl cache
				if($autoUpdateCache) {
					$this->saveAclToCache();
				}
			}
		}
		return $this->acl;
	}
	
	/**
	 * Returns the resource, else return false
	 * @param string $name The resource name
	 * @return AclResource | false
	 */
	public function getResource($name)
	{
		// if the name is not empty
		if(!empty($name)) {
			$dql = "SELECT r FROM Entities\AclResource r WHERE r.resource = ?1 AND r.deleted = false";
			$query = $this->getEntityManagerService()->createQuery($dql);
			$query->setParameters(1, $name);
			try {
				$checkResource = $query->getSingleResult();
				return $checkResource;
			}
			// no resource found
			catch(\Doctrine\ORM\NoResultException $e) {}
		}
		return null;
	}
	
	protected function createAndSaveResource($name)
	{
		$resource = null;
		// if the resource is a string
		if(is_string($name)) {
			// try to get the resource (if already exists)
			$resource = $this->getResource($name);
			// if resource doesn't exists
			if(empty($resource)) {
				// create a new one
				$resource = new AclResource($name);
				// get entity manager
				$em = $this->getEntityManagerService();
				// save the new resource
				$em->persist($resource);
				$em->flush();
			}
		}
		return $resource;
	}

	public function addResource($resource, $parent = null, $autoUpdateCache = true)
	{
		// if the resource is not empty and is not already registered in the acl
		if(
			!empty($resource)
			&& (!$resource instanceof AclResource || !$this->acl->has($resource))
		) {
			// if the parent is null and the resource is a AclResource
			if(empty($parent) && $resource instanceof AclResource) {
				$parent = $resource->getParent();
			}
			// if the parent resource is a string
			if(is_string($parent)) {
				// create and save the parent resource
				$parent = $this->createAndSaveResource($parent);
			}
			// if the parent resource is a AclResource and the current acl doesn't have the resource
			if($parent instanceof AclResource && !$this->acl->has($parent)) {
				// add resource to the current acl
				$this->addResource($parent, null, false);
			}
			// if the resource is a string
			if(is_string($resource)) {
				// create and save the resource
				$resource = $this->createAndSaveResource($resource);
			}
			// if the resource is a AclResource and the current acl doesn't have the resource
			if($resource instanceof AclResource && !$this->acl->has($resource)) {
				// add resource to the current acl
				$this->acl->addResource($resource, $parent);
				// update the acl cache
				if($autoUpdateCache) {
					$this->saveAclToCache();
				}
			}
		}
		return $this->acl;
	}
	
	/**
	 * Returns the rule, else return false
	 * @return AclResource | false
	 */
	public function getRule($action, AclRole $role, AclResource $resource, $privilege = 'use')
	{
		// if the parameters are not empty (except the name)
		if(!empty($action) && !empty($role) && !empty($resource) && !empty($privilege)) {
			$dql = "SELECT r FROM Entities\AclRule r WHERE";
			$dql .= " r.action = ?1";
			$dql .= " AND r.role = ?2";
			$dql .= " AND r.resource = ?3";
			$dql .= " AND r.privilege = ?4";
			$dql .= " AND r.deleted = false";
			$query = $this->getEntityManagerService()->createQuery($dql);
			$query->setParameters(1, $action);
			$query->setParameters(2, $role);
			$query->setParameters(3, $resource);
			$query->setParameters(4, $privilege);
			try {
				$checkRule = $query->getSingleResult();
				return $checkRule;
			}
			// no rule found
			catch(\Doctrine\ORM\NoResultException $e) {}
		}
		return null;
	}
	
	protected function createAndSaveRule($action, $role, $resource, $privilege, $name)
	{
		$rule = null;
		if(!empty($action) && !empty($role) && !empty($resource) && !empty($privilege)) {
			// try to get the rule (if already exists)
			$rule = $this->getRule($action, $role, $resource, $privilege);
			// if rule doesn't exists
			if(empty($rule)) {
				// create a new one
				$rule = new AclRule($action, $role, $resource, $privilege, $name);
				// get entity manager
				$em = $this->getEntityManagerService();
				// save the new rule
				$em->persist($rule);
				$em->flush();
			}
		}
		return $rule;
	}

	public function addRule($action, AclRole $role, AclResource $resource, $privilege = 'use', $name = null, $autoUpdateCache = true)
	{
		// if the parameters are not empty (except the name)
		if(!empty($action) && !empty($role) && !empty($resource) && !empty($privilege)) {
			// create and save the rule
			$rule = $this->createAndSaveRule($action, $role, $resource, $privilege, $name);
			// add the rule to the current acl
			if($rule->getAllow()) {
				$this->acl->allow($role, $resource, $privilege);
			}
			else {
				$this->acl->deny($role, $resource, $privilege);
			}
			// update the acl cache
			if($autoUpdateCache) {
				$this->saveAclToCache();
			}
		}
		return $this->acl;
	}
	
	public function loadAcl()
	{
		// if the acl is empty
		if(empty($this->acl)) {
			// try to get it from the cache
			$this->loadAclFromCache();
			// if the acl is still empty
			if(empty($this->acl)) {
				// load it from the db
				$this->loadFromDb();
				// cache it
				$this->saveAclToCache();
			}
		}
		return $this->acl;
	}
	
	protected function loadFromDb()
	{
		// create a new, then fill it with the db datas
		$this->acl = new \Zend_Acl();
		// get the entity manager service
		$em = $this->getEntityManagerService();
		// create the query
		$dql = "SELECT r FROM Entities\AclRule r WHERE r.deleted = false";
		$query = $em->createQuery($dql);
		try {
			// run the query
			$rules = $query->getResult();
		}
		catch(\Doctrine\ORM\NoResultException $e) {}
		// if the query has return something
		if(isset($rules) && !empty($rules)) {
			// for each rule
			foreach($rules as $rule) {

				// get the role
				$role = $rule->getRole();
				$parentRole = $role;
				// while role has a parent role
				while($parentRole = $parentRole->getParent())
				{
					$this->addRole($parentRole);
				}
				// add the role
				$this->addRole($role);

				// get the resource
				$resource = $rule->getResource();
				if(!empty($resource)) {
					$parentResource = $resource;
					// while resource has a parent resource
					while($parentResource = $parentResource->getParent())
					{
						$this->addResource($parentResource);
					}
					// add the resource
					$this->addResource($resource);
				}

				// get the privilege
				$privilege = $rule->getPrivilege();

				// get the action : allow or deny
				$action = $rule->getAllow();

				// add the rule
				if($action) {
					$this->acl->allow($role, $resource, $privilege);
				}
				else {
					$this->acl->deny($role, $resource, $privilege);
				}
			}
		}
		unset($rules);
		//Debug::dump($this->acl, true);
		return $this->acl;
	}
	
	protected function loadAclFromCache()
	{
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				// if the cache contains the acl
				if($cache->test(self::$cacheAclId)) {
					// get the acl from cache
					$this->acl = unserialize($cache->load(self::$cacheAclId));
				}
			}
		}
		return $this->acl;
	}
	
	protected function saveAclToCache()
	{
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				$id = self::$cacheAclId;
				$content = serialize($this->acl);
				// save the acl to the cache
				if(!$cache->save($content, $id)) {
					throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
				}
			}
		}
		return $this->acl;
	}
}
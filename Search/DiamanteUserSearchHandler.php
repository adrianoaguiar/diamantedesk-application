<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */
namespace Diamante\DeskBundle\Search;

use Diamante\DeskBundle\Model\User\DiamanteUserRepository;
use Diamante\DeskBundle\Model\User\User;
use Diamante\DeskBundle\Model\User\UserDetailsService;
use Oro\Bundle\FormBundle\Autocomplete\SearchHandlerInterface;
use Oro\Bundle\UserBundle\Autocomplete\UserSearchHandler;

class DiamanteUserSearchHandler implements SearchHandlerInterface
{
    const ID_FIELD_NAME = 'id';

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var DiamanteUserRepository
     */
    protected $diamanteUserRepository;

    /**
     * @var UserSearchHandler
     */
    protected $oroUserSearchHandler;

    public function __construct(
        $entityName,
        UserDetailsService  $diamanteUserDetailsService,
        DiamanteUserRepository $diamanteUserRepository,
        UserSearchHandler   $oroUserSearchHandler,
        array $properties
    )
    {
        $this->properties             = $properties;
        $this->entityName             = $entityName;
        $this->userDetailsService     = $diamanteUserDetailsService;
        $this->diamanteUserRepository = $diamanteUserRepository;
        $this->oroUserSearchHandler   = $oroUserSearchHandler;
    }

    /**
     * Converts item into an array that represents it in view.
     *
     * @param mixed $item
     * @return array
     */
    public function convertItem($item)
    {
        if ($item instanceof User) {
            $convertedItem = $this->convertItemFromObject($item);
        } else {
            $convertedItem = $item;
        }

        return $convertedItem;
    }

    /**
     * Gets search results, that includes found items and any additional information.
     *
     * @param string $query
     * @param int $page
     * @param int $perPage
     * @param bool $searchById
     * @return array
     */
    public function search($query, $page, $perPage, $searchById = false)
    {
        $items = array();

        $diamanteUsers = $this->diamanteUserRepository->searchByInput($query, $this->properties);
        $oroUsers      = $this->oroUserSearchHandler->search($query, $page, $perPage, $searchById);

        if (!empty($diamanteUsers)) {
            $convertedDiamanteUsers = $this->convertUsers($diamanteUsers, User::TYPE_DIAMANTE);
            $items = array_merge($items, $convertedDiamanteUsers);
        }

        if (!empty($oroUsers['results'])) {
            $convertedOroUsers = $this->convertUsers($oroUsers['results'], User::TYPE_ORO);
            $items = array_merge($items, $convertedOroUsers);
        }

        return array(
            'results' => $items,
            'more'    => false
        );
    }

    /**
     * Gets properties that should be displayed
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Gets entity name that is handled by search
     *
     * @return mixed
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @param User $item
     * @return array
     */
    protected function convertItemFromObject( User $item)
    {
        $converted = array();

        $obj = $this->userDetailsService->fetch($item);
        $converted[self::ID_FIELD_NAME] = $obj->getId();

        foreach ($this->properties as $property) {
            $converted[$property] = $this->getPropertyValue($property, $obj);
        }

        if (empty($converted['fullName']) || $converted['fullName'] === ' ') {
            $converted['fullName'] = $converted['email'];
        }

        return $converted;
    }

    /**
     * @param array $users
     * @param $type
     * @return array
     */
    protected function convertUsers(array $users, $type)
    {
        $result = array();

        foreach ($users as $user) {
            $converted = array();

            foreach($this->properties as $property) {
                $converted[$property]  = $this->getPropertyValue($property, $user);
            }

            $converted['type'] = $type;
            if (is_array($user)) {
                $converted[self::ID_FIELD_NAME] = $type . User::DELIMITER . $user[self::ID_FIELD_NAME];
            } else {
                $converted[self::ID_FIELD_NAME] = $type . User::DELIMITER . $user->getId();
            }

            if ($type === User::TYPE_DIAMANTE) {
                $converted['avatar'] = $this->buildGravatarLink($converted['email']);
            }

            $result[] = $converted;
        }

        return $result;
    }

    /**
     * @param string       $name
     * @param object|array $item
     * @return mixed
     */
    protected function getPropertyValue($name, $item)
    {
        $result = null;

        if (is_object($item)) {
            $method = 'get' . str_replace(' ', '', str_replace('_', ' ', ucwords($name)));
            if (method_exists($item, $method)) {
                $result = $item->$method();
            } elseif (isset($item->$name)) {
                $result = $item->$name;
            }
        } elseif (is_array($item) && array_key_exists($name, $item)) {
            $result = $item[$name];
        }

        return $result;
    }

    /**
     * @param $email
     * @param bool $secure
     * @return string
     */
    private function buildGravatarLink($email, $secure = false)
    {
        $hash = md5(strtolower(trim($email)));
        $schema = (bool)$secure ? 'https' : 'http';

        //Query parameters are:
        // s - size, default oro avatar size is 58px,
        // d - default image if no configured image found for given hash.
        // for details see https://en.gravatar.com/site/implement/images/

        $link = sprintf('%s://gravatar.com/avatar/%s.jpg?s=%d&d=identicon', $schema, $hash, 16);

        return $link;
    }
} 
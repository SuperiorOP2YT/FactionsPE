<?php
/*
 *   FactionsPE: PocketMine-MP Plugin
 *   Copyright (C) 2016  Chris Prime
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace factions\data;

use pocketmine\level\Level;
use pocketmine\level\Position;

use factions\manager\Factions;
use factions\manager\Members;
use factions\FactionsPE;

class FactionData extends Data {
		
	/**
	 * @var string
	 */
	protected $name;

	/**
	 * Generated by FactionsPE::generateFactionID();
	 * @var id
	 */
	protected $id;

	/**
     * @var array $flags Flag => bool 
     */
    protected $flags = [];

	/**
     * The perm overrides are modifications to the default values.
     * @var Perm[]
     */ 
    protected $perms = [];

	/**
	 * Why not to save Member instances here? Because we need to get fresh one from storage
	 * in case it changes
	 * 
	 * @var string[] 
	 */
	protected $members = [];

	/** 
     * Factions can optionally set a description for themselves.
     * This description can for example be seen in territorial alerts.
     * Null means the faction has no description.
     * @var string
     */
	protected $description;

	/** 
     * Factions can optionally set a message of the day.
     * This message will be shown when logging on to the server.
     * Null means the faction has no motd
     * @var string $motd 
     */
	protected $motd;

	/**
     * We store the creation date for the faction. It can be displayed on info pages etc. 
     * @var int $createdAt 
     */
	protected $createdAt;

	/**
     * Factions can optionally set a home location.
     * If they do their members can teleport there using /f home
     * Null means the faction has no home.
     * @var Position|null
     */
	protected $home;

	/** 
     * Factions usually do not have a powerboost. It defaults to 0.
     * The powerBoost is a custom increase/decrease to default and maximum power.
     * Null means the faction has powerBoost (0).
     */
    protected $powerBoost = 0;

    /**
     * Faction ID => Relation ID 
     * @var array $relationWishes 
     */
    protected $relationWishes = [];

    /**
     * @var string[]
     */
    protected $invitedPlayers = [];

	public function __construct(array $source) {
		// required fields
		$this->name = $source["name"];
		$this->id = $source["id"];
		if(isset($source["members"])) {
			foreach($source["members"] as $member) {
				if(!($member instanceof IFPlayer)) {
					$member = Members::get($member);
				}
				if($member->hasFaction() && $member->getFaction()->getId() !== $this->id){
						throw new Exception("can not assign player '{$member->getName()}' to new faction while he is member of other faction");					
				}
				$member->setFaction(Factions::getById($this->id));
				$this->members[] = $member->getName();
			}
		}
		// optional fields
		$this->createdAt = $source["createdAt"] ?? time();
		$this->description = $source["description"] ?? null;
		$this->motd = $source["motd"] ?? null;
		$this->powerBoost = $source["powerBoost"] ?? $this->powerBoost;
		$this->invitedPlayers = $source["invitedPlayers"] ?? [];
		$this->perms = $source["perms"] ?? [];
		$this->flags = $source["flags"] ?? [];
		$this->relationWishes = $source["relationWishes"] ?? [];
		if(isset($source["home"])) {
			$p = explode($source["home"]);
			if(($level = FactionsPE::get()->getServer()->getLevelByName($p[3]))) {
				$this->home = new Position((float) $p[0], (float) $p[1], (float) $p[2], $level);
			} else {
				FactionsPE::get()->getLogger()->warning(Localizer::trans("error.faction-load-invalid-level", $this->name, $source["home"]));
			}
		}
	}

	/**
	 * Puts all faction data, necessary to save, into array
	 */
	public function __toArray() : array {
		return [
			"name" => $this->name,
			"id" => $this->id,
			"flags" => $this->getFlags(),
			"perms" => $this->getPermissions(),
			"members" => $this->members,
			"powerBoost" => $this->powerBoost,
			"relationWishes" => $this->relationWishes,
			"createdAt" => $this->createdAt,
			"motd" => $this->motd,
			"description" => $this->description,
			"invitedPlayers" => $this->invitedPlayers
		];
	}

	public function save() {
		if( ($faction = Factions::getById($this->id)) ) {
			FactionsPE::get()->getDataProvider()->saveFaction($faction);
		} else {
			throw new \Exception("faction data is not assigned to valid faction");
		}
	}

	/*
	 * ----------------------------------------------------------
	 * ID
	 * ----------------------------------------------------------
	 */

	public function getId() : string {
		return $this->id;
	}

	/*
	 * ----------------------------------------------------------
	 * NAME
	 * ----------------------------------------------------------
	 */

	public function getName() : string {
		return $this->name;
	}

	public function setName(string $name) {
		$this->name = $name;
		$this->changed();
	}

	/*
	 * ----------------------------------------------------------
	 * DESCRIPTION
	 * ----------------------------------------------------------
	 */

	public function getDescription() : string {
		return $this->description;
	}

	public function setDescription(string $description) {
		$this->description = $description;
	}

	public function hasDescription() : bool {
		return $this->description !== null;
	}

	public function removeDescription() {
		$this->description = null;
	}

	/*
	 * ----------------------------------------------------------
	 * MOTD
	 * ----------------------------------------------------------
	 */

	public function getMotd() {
		return $this->motd;
	}

	public function setMotd(string $motd) {
		$this->motd = $motd;
	}

	public function hasMotd() : bool {
		return $this->motd !== null;
	}

	public function removeMotd() {
		$this->motd = null;
	}

	/*
	 * ----------------------------------------------------------
	 * HOME
	 * ----------------------------------------------------------
	 */

	public function getHome() {
		return $this->home;
	}

	public function setHome(Position $home) {
		$this->home = $home;
	}

	public function hasHome() : bool {
		return $this->home instanceof Position && $this->home->isValid();
	}

	public function isHomeSet() : bool {
		return $this->home instanceof Position;
	}

	/*
	 * ----------------------------------------------------------
	 * CREATED AT
	 * ----------------------------------------------------------
	 */

	public function getCreatedAt() : int {
		return $this->createdAt;
	}

	/*
	 * ----------------------------------------------------------
	 * POWER BOOST
	 * ----------------------------------------------------------
	 */

	public function getPowerBoost() : int {
		return $this->powerBoost;
	}

	public function setPowerBoost(int $power) {
		$this->powerBoost = $power;
	}

	public function hasPowerBoost() : bool {
		return $this->powerBoost === 0;
	}

	/*
	 * ----------------------------------------------------------
	 * MEMBERS
	 * ----------------------------------------------------------
	 */

	public function getMembers() : array {
		return $this->members;
	}

	/*
	 * ----------------------------------------------------------
	 * FLAGS
	 * ----------------------------------------------------------
	 */

	public function getFlags() : array {
		return $this->flags;
	}

	/*
	 * ----------------------------------------------------------
	 * PERMS
	 * ----------------------------------------------------------
	 */

	public function getPermissions() : array {
		return $this->perms;
	}

	/*
	 * ----------------------------------------------------------
	 * RELATION WISHES
	 * ----------------------------------------------------------
	 */

	public function getRelationWishes() : array {
		return $this->relationWishes;
	}

	/*
	 * ----------------------------------------------------------
	 * INVITATION
	 * ----------------------------------------------------------
	 */

	public function getInvitedPlayers() : array {
    	return $this->invitedPlayers;
    }

}
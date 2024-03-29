<?php
	require_once("../phpLib/Default.php");
	require_once("../phpLib/Organization_server_fns.php");
	require_once("../phpLib/Grant_server_fns.php");
	require_once("../phpLib/ProcessGrant_fns.php");
	require_once("../phpLib/ProcessDataInfo_fns.php");
	
	function insert_contact_in_db($db, &$contact) {
		$query = "INSERT INTO contact VALUES (?, ?, ?, ?, ?, ?)";
		$stmt = $db->prepare($query);
		
		$stmt->bind_param("isssss",
			$null = NULL,
			FilterString($contact["person"]),
			FilterString($contact["address"]),
			FilterString($contact["telephone"]),
			FilterString($contact["fax"]),
			FilterString($contact["email"])
		);
		$success = $stmt->execute();
		$stmt->close();
		
		$contact["id"] = $db->insert_id;
		return $success;
	}
	
	function insert_organization_in_db($db, &$org) {
		$query = "INSERT INTO organization VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$stmt = $db->prepare($query);
		
		$stmt->bind_param("isssssisiiii",
			$null = NULL,
			FilterString($org["name"]),
			FilterString($org["websiteEn"]),
			FilterString($org["websiteCh"]),
			FilterString($org["type"]),
			FilterString($org["originalCountry"]),
			FilterString($org["granteeType"]),
			$org["acceptPublic"],
			$org["numOffices"],
			$org["cnContact"]["id"],
			$org["hqContact"]["id"],
			$org["datainfo"]["id"]
		);
		$success = $stmt->execute();
		$stmt->close();
		
		$org["id"] = $db->insert_id;
		return $success;
	}
	
	function insert_org_geos_in_db($db, $org_id, &$geos) {
		$query = "INSERT INTO organization_geos VALUES (?, ?, ?)";
		$stmt = $db->prepare($query);
		
		for ($i = 0, $len = count($geos); $i < $len; $i++) {
			$stmt->bind_param("iis",
				$null = NULL,
				$geos[$i]["org_id"] = $org_id,
				FilterString($geos[$i]["geo"])
			);
			$success = $stmt->execute();
			$geos[$i]["id"] = $db->insert_id;
			
			if (!$success)
				return false;
		}
		$stmt->close();
		return true;
	}
	
	function insert_org_area_subareas_in_db($db, $area_id, $subareas) {
		$query = "INSERT INTO area_subareas VALUES (?, ?, ?)";
		$stmt = $db->prepare($query);
		
		foreach ($subareas as $index => $subarea) {
			$stmt->bind_param("iis",
				$null = NULL,
				$area_id,
				FilterString($subarea)
			);
			$success = $stmt->execute();
			if (!$success)
				return false;
		}
		$stmt->close();
		return true;
	}
	
	function insert_org_areas_in_db($db, $org_id, &$areas) {
		$query = "INSERT INTO organization_areas VALUES (?, ?, ?)";
		$stmt = $db->prepare($query);
		
		foreach ($areas as $index => &$area_item) {
			$area_item["org_id"] = $org_id;
			$stmt->bind_param("iis",
				$null = NULL,
				$org_id,
				FilterString($area_item["area"])
			);
			$success = $stmt->execute();
			$area_item["id"] = $db->insert_id;
			if (!$success || !insert_org_area_subareas_in_db($db, $area_item["id"], $area_item["subareas"]))
				return false;
		}
		$stmt->close();
		return true;
	}
	
	function insert_org_assets_in_db($db, $org_id, &$assets) {
		$query = "INSERT INTO organization_assets VALUES (?, ?, ?, ?)";
		$stmt = $db->prepare($query);
		
		for ($i = 0, $len = count($assets); $i < $len; $i++) {
			$stmt->bind_param("iiii",
				$null = NULL,
				$assets[$i]["org_id"] = $org_id,
				$assets[$i]["year"],
				$assets[$i]["amount"]
			);
			$success = $stmt->execute();
			$assets[$i]["id"] = $db->insert_id;
			if (!$success)
				return false;
		}
		$stmt->close();
		return true;
	}
	
	function insert_org_giving_in_db($db, $org_id, &$giving) {
		$query = "INSERT INTO organization_giving VALUES (?, ?, ?, ?, ?)";
		$stmt = $db->prepare($query);
		
		for ($i = 0, $len = count($giving); $i < $len; $i++) {
			$stmt->bind_param("iiiii",
				$null = NULL,
				$giving[$i]["org_id"] = $org_id,
				$giving[$i]["year"],
				$giving[$i]["world"],
				$giving[$i]["china"]
			);
			$success = $stmt->execute();
			$giving[$i]["id"] = $db->insert_id;
			if (!$success)
				return false;
		}
		$stmt->close();
		return true;
	}
	
	function insert_org_in_db($db, &$org) {
		// store it in database
		// INSERT organization as following steps:
		// 1. Insert cncontact
		// 2. Insert hqcontact
		// 3. Insert datainfo
		// 4. Insert organization
		// 5. Insert geos
		// 6. Insert subareas
		// 7. Insert assets
		// 8. Insert giving
		
		$insert_cnContact = insert_contact_in_db($db, $org["cnContact"]);
		$insert_hqContact = insert_contact_in_db($db, $org["hqContact"]);
		$insert_datainfo = insert_datainfo_in_db($db, $org["datainfo"]);
		$insert_organization = insert_organization_in_db($db, $org);
		
		$insert_org_geos = insert_org_geos_in_db($db, $org["id"], $org["geos"]);
		$insert_org_areas = insert_org_areas_in_db($db, $org["id"], $org["areas"]);
		$insert_org_assets = insert_org_assets_in_db($db, $org["id"], $org["assets"]);
		$insert_org_giving = insert_org_giving_in_db($db, $org["id"], $org["giving"]);
		
		return $insert_cnContact && $insert_hqContact && $insert_datainfo && $insert_organization &&
				$insert_org_geos && $insert_org_areas && $insert_org_assets && $insert_org_giving;
	}
	
	function update_contact_in_db($db, $contact) {
		$query =	sprintf("UPDATE contact SET
								person_name = '%s',
								address = '%s',
								telephone = '%s',
								fax = '%s',
								email = '%s'
							WHERE id = %d",
							FilterString($contact["person"]),
							FilterString($contact["address"]),
							FilterString($contact["telephone"]),
							FilterString($contact["fax"]),
							FilterString($contact["email"]),
							$contact["id"]
					);
		return $db->query($query);
	}
	
	function update_organization_in_db($db, $org) {
		$query =	sprintf("UPDATE organization SET
								organization_name = '%s',
								english_website = '%s',
								chinese_website = '%s',
								organization_type = '%s',
								original_country = '%s',
								grantee_type = '%s',
								accept_public = '%d',
								num_offices_china = '%d',
								china_contact_id = '%d',
								hq_contact_id = '%d',
								datainfo_id = '%d'
							WHERE id = %d",
							FilterString($org["name"]),
							FilterString($org["websiteEn"]),
							FilterString($org["websiteCh"]),
							FilterString($org["type"]),
							FilterString($org["originalCountry"]),
							FilterString($org["granteeType"]),
							$org["acceptPublic"],
							FilterString($org["numOffices"]),
							$org["cnContact"]["id"],
							$org["hqContact"]["id"],
							$org["datainfo"]["id"],
							$org["id"]
					);
		return $db->query($query);
	}
	
	function update_org_geos_in_db($db, $org_id, &$geos) {
		$delete_org_geos = delete_org_geos_in_db($db, $org_id);
		$insert_org_geos = insert_org_geos_in_db($db, $org_id, $geos);
		return $delete_org_geos && $insert_org_geos;
	}
	
	function update_org_areas_in_db($db, $org_id, &$areas) {
		$delete_org_areas = delete_org_areas_in_db($db, $org_id);
		$insert_org_areas = insert_org_areas_in_db($db, $org_id, $areas);
		return $delete_org_areas && $insert_org_areas;
	}
	
	function update_org_assets_in_db($db, $org_id, &$assets) {
		$delete_org_assets = delete_org_assets_in_db($db, $org_id);
		$insert_org_assets = insert_org_assets_in_db($db, $org_id, $assets);
		return $delete_org_assets && $insert_org_assets;
	}
	
	function update_org_giving_in_db($db, $org_id, &$giving) {
		$delete_org_giving = delete_org_giving_in_db($db, $org_id);
		$insert_org_giving = insert_org_giving_in_db($db, $org_id, $giving);
		return $delete_org_giving && $insert_org_giving;
	}
	
	function update_org_in_db($db, &$org) {
		$update_cnContact = update_contact_in_db($db, $org["cnContact"]);
		$update_hqContact = update_contact_in_db($db, $org["hqContact"]);
		$update_datainfo = update_datainfo_in_db($db, $org["datainfo"]);
		$update_organization = update_organization_in_db($db, $org);
		
		$update_org_geos = update_org_geos_in_db($db, $org["id"], $org["geos"]);
		$update_org_areas = update_org_areas_in_db($db, $org["id"], $org["areas"]);
		$update_org_assets = update_org_assets_in_db($db, $org["id"], $org["assets"]);
		$update_org_giving = update_org_giving_in_db($db, $org["id"], $org["giving"]);
		
		return $update_cnContact && $update_hqContact && $update_datainfo && $update_organization &&
				$update_org_geos && $update_org_areas_subareas && $update_org_assets && $update_org_giving;
	}
	
	function delete_contact_in_db($db, $contact) {
		$query = "DELETE FROM contact WHERE id = $contact[id]";
		return $db->query($query);
	}
	
	function delete_organization_in_db($db, $org) {
		$query = "DELETE FROM organization WHERE id = $org[id]";
		return $db->query($query);
	}
	
	function delete_org_geos_in_db($db, $org_id) {
		$query = "DELETE FROM organization_geos WHERE organization_id = $org_id";
		return $db->query($query);
	}
	
	function delete_org_area_subareas_in_db($db, $area_id) {
		$query = "DELETE FROM area_subareas WHERE area_id = $area_id";
		return $db->query($query);
	}
	
	function delete_org_areas_in_db($db, $org_id, $areas) {
		foreach ($areas as $area_item) {
			if ($area_item["org_id"] == $org_id) {
				$delete_org_area_subareas = delete_org_area_subareas_in_db($db, $area_item["id"]);
				if (!$delete_org_area_subareas)
					return false;
			}
		}
		$query = "DELETE FROM organization_areas WHERE org_id = $org_id";
		return $db->query($query);
	}
	
	function delete_org_assets_in_db($db, $org_id) {
		$query = "DELETE FROM organization_assets WHERE organization_id = $org_id";
		return $db->query($query);
	}
	
	function delete_org_giving_in_db($db, $org_id) {
		$query = "DELETE FROM organization_giving WHERE organization_id = $org_id";
		return $db->query($query);
	}
	
	function delete_grants_in_db($db, $org_id) {
		$delete_grants = true;
		$grants = get_grants_from_db($org_id);
		if (($count = count($grants)) > 0) {
			for ($i = 0; $i < $count; $i++)
				$delete_grants &= delete_grant_in_db($db, $grants[$i]);
		}
		return $delete_grants;
	}
	
	function delete_org_in_db($db, $org) {
		$delete_cnContact = delete_contact_in_db($db, $org["cnContact"]);
		$delete_hqContact = delete_contact_in_db($db, $org["hqContact"]);
		$delete_datainfo = delete_datainfo_in_db($db, $org["datainfo"]);
		$delete_organization = delete_organization_in_db($db, $org);
		
		$delete_org_geos = delete_org_geos_in_db($db, $org["id"]);
		$delete_org_areas = delete_org_areas_in_db($db, $org["id"], $org["areas"]);
		$delete_org_assets = delete_org_assets_in_db($db, $org["id"]);
		$delete_org_giving = delete_org_giving_in_db($db, $org["id"]);
		
		$delete_grants = delete_grants_in_db($db, $org["id"]);
		
		return $delete_cnContact && $delete_hqContact && $delete_datainfo && $delete_organization &&
				$delete_org_geos && $delete_org_areas && $delete_org_assets && $delete_org_giving && $delete_grants;
	}
	
	function process_organization(&$org, $action) {
		if ($EchoOrgTest = false)
			output($org);
		
		$db = connect_db();
		$db->autocommit(false);
		switch ($action) {
			case "add":
			default:
				echo "<a href='./OrganizationForm.php?action=add'> Add another New Organization </a>";
				if (insert_org_in_db($db, $org)) {
					$db->commit();
					echo "<p> Succeeded to Insert the Organization into Database. </p>";
					output($org);
				} else {
					$db->rollback();
					echo "<p> Failed to Insert the Organization into Database! </p>";
				}
				break;
			
			case "update":
				echo "<a href='./ListOrganization.php'> Back to List Organization </a>";
				if (update_org_in_db($db, $org)) {
					$db->commit();
					echo "<p> Succeeded to Update the Organization in Database. </p>";
					output($org);
				} else {
					$db->rollback();
					echo "<p> Failed to Update the Organization in Database! </p>";
				}
				break;
			
			case "delete":
				echo "<a href='./ListOrganization.php'> Back to List Organization </a>";
				$org = get_org_from_db($db, $org["id"]);
				if (isset($org["id"]) && delete_org_in_db($db, $org)) {
					$db->commit();
					echo "<p> Succeeded to Delete the Organization in Database. </p>";
					output($org);
				} else {
					$db->rollback();
					echo "<p> Failed to Delete the Organization in Database! </p>";
				}
				break;
		}
		$db->autocommit(true);
		$db->close();
	}
?>
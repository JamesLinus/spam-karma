<?php
/**********************************************************************************************
 Spam Karma (c) 2015 - http://github.com/strider72/spam-karma

 This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

************************************************************************************************/
?><?php
define ("SK_BLACKLIST_TABLE", "sk_blacklist");

global $sk_blacklist;
if (! isset($sk_blacklist))
	$sk_blacklist = new sk_blacklist;

class sk_blacklist
{
	function __construct()
	{
	
	}
	
	function add_entry($type, $value, $score = 100, $user_reviewed = "no", $added_by = "unknown", $trust = 100)
	{
		global $wpdb;
		
		if (($type == "domain_black" || $type == "domain_white") 
				&& ($grey_rslt = $wpdb->get_results("SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE `type` = 'domain_grey' AND `value` = '$value'")))
		{
			$this->log_msg(__("Greylist match. Skipping blacklist entry insertion: ", 'spam-karma') . "<em>$type</em> - <em> $value</em>.", 7);
			return 0;
		}
		
		$score = min(100, max($score, 0));
		
		$value = trim($value);
		if (empty($value))
		{
			$this->log_msg(__("Cannot add blacklist entry. Please fill in a value.", 'spam-karma'), 7);
			return false;
		}
		elseif ($wpdb->get_var("SELECT COUNT(*) FROM `". SK_BLACKLIST_TABLE . "` WHERE `type`='$type' AND `value`='" . sk_escape_string($value) . "' LIMIT 1"))
		{
			$this->log_msg(__("Skipping duplicate blacklist entry: ", 'spam-karma') . "<em>$type</em> - <em> $value</em>.", 7);
		}
		else
		{
			if ($wpdb->query("INSERT INTO `". SK_BLACKLIST_TABLE . "` SET `type`='$type', `value`='" . sk_escape_string($value) . "', `added` = NOW(), `last_used` = NOW(), `score` = $score, `trust` = $trust, `user_reviewed` = '$user_reviewed', `added_by` = '$added_by', `comments` = ''"))
					$this->log_msg(__("Successfully inserted blacklist entry: ", 'spam-karma') . "<em>$type</em> - <em>$value</em>.", 3);
			else
					$this->log_msg(__("Failed to insert blacklist entry: ", 'spam-karma') . "<em>$type</em> - <em>$value</em>.", 8, true);
		}
		
		return $wpdb->insert_id;
	}

	function auto_add($type, $value, $score = 100, $user_reviewed = "no", $added_by = "unknown", $trust = 100)
	{
		global $wpdb;
		
		$score = min(100, max($score, 0));
		
		if (empty($value))
			$this->log_msg(__("Cannot add blacklist entry. Please fill in a value.", 'spam-karma'), 7);
		elseif	 (($type == "domain_black" || $type == "domain_white")
			&& ($grey_rslt = $wpdb->get_results("SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE `type` = 'domain_grey' AND `value` = '$value'")))
		{
			$this->log_msg(__("Greylist match. Skipping blacklist entry insertion: ", 'spam-karma') . "<em>$type</em> - <em> $value</em>.", 6);
			return;
		}
		elseif ($row = $wpdb->get_row("SELECT `id`, `score` FROM `". SK_BLACKLIST_TABLE . "` WHERE `type`='$type' AND `value`='" . sk_escape_string($value) . "' LIMIT 1"))
		{
			if (($old_score = $row->score) >= 100)
				return true;
			$query = "UPDATE `". SK_BLACKLIST_TABLE . "` SET ";
			$query_where = " WHERE `id` = " . $row->id;
		}
		else
		{
			$query = "INSERT INTO `". SK_BLACKLIST_TABLE . "` SET `type`='$type', `value`='" . sk_escape_string($value) . "', `added` = NOW(), `last_used` = NOW(), `trust` = $trust, `user_reviewed` = '$user_reviewed', `added_by` = '$added_by', `comments` = '',";
			$query_where = "";
			$old_score = 0;
		}
		
		$score = round(max($old_score, (3 * $old_score + $score) / 4));
		
		$wpdb->query($query . "`score` = $score" . $query_where);
		
		if (! mysql_error())
		{
			$this->log_msg(__("Successfully inserted/updated blacklist entry: ", 'spam-karma') . "<em>$type</em> - <em>$value</em>. " . __("Current score: ", 'spam-karma') . $score, 3);
			return true;
		}
		else
			$this->log_msg(__("Failed to insert blacklist entry: ", 'spam-karma') . "<em>$type</em> - <em>$value</em>.", 8, true);
	}



	function match_entries($match_type, $match_value, $strict = true, $min_score = 0, $limit = 0)
	{
		global $wpdb;
		
		if ($strict)
			$sql_match = "= '" . sk_escape_string($match_value) . "'";
		else
			$sql_match = "LIKE '%". sk_escape_string($match_value) . "%'";

		 switch ($match_type)
		 {
			case 'url':
			case 'url_black':
			case 'url_white':
				if ($match_type == 'url_black')
				{
					$query_where = "(`value` " . strtolower($sql_match) . " AND (`type` = 'domain_black')) OR (`id` IN(";
					$query_where_regex = "`type` = 'regex_black'";
				}
				elseif($match_type == 'url_white')
				{
					$query_where = "(`value` " . strtolower($sql_match) . " AND `type` = 'domain_white') OR (`id` IN(";
					$query_where_regex = "`type` = 'regex_white'";
				}
				else
				{
					$query_where = "(`value` " . strtolower($sql_match) . " AND (`type` = 'domain_black' OR `type` = 'domain_white' OR `type` = 'domain_grey')) OR (`id` IN(";
					$query_where_regex = "`type` = 'regex_white' OR `type` = 'regex_black'";
				}
				
				if ($regex_recs = $wpdb->get_results("SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE $query_where_regex"))
					foreach($regex_recs as $regex_rec)
					{
						//echo $regex_rec->value, " ?match? " , $match_value;
						if (preg_match($regex_rec->value, $match_value))
							$query_where .= $regex_rec->id . ", ";
					}
				$query_where .= "-1))";
			break;
						
			case 'regex_match':
			case 'regex_content_match':
				if ($match_type == 'regex_match')
					$type = 'regex';
				else
					$type = 'regex_content';
				$query_where = "`id` IN(";
				if ($regex_recs = $wpdb->get_results("SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE `type` = '${type}_white' OR `type` = '${type}_black'"))
					foreach($regex_recs as $regex_rec)
					{
						//echo $regex_rec->value, " ?match? " , $match_value;
						$res = @preg_match($regex_rec->value, $match_value);
						if ($res === FALSE)
							$this->log_msg(sprintf(__("Regex ID: %d (<code>%s</code>) appears to be an invalid regex string! Please fix it in the Blacklist control panel.", 'spam-karma'), $regex_rec->id, $regex_rec->value), 7);
						elseif ($res)
							$query_where .= $regex_rec->id . ", ";
					}
				$query_where .= "-1)";
			break;

			case 'domain_black':
			case 'ip_black':
			case 'domain_white':
			case 'ip_white':
				if (($match_type == 'domain_black' || $match_type == 'domain_white')
					&& ($grey_rslt = $wpdb->get_results("SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE `type` = 'domain_grey' AND `value` $sql_match")))
				{
					$query_where = "";
					$this->log_msg(__("Grey blacklist match: ignoring.", 'spam-karma'), 6);				
				}
				else
					$query_where = "(`value` $sql_match AND `type` = '" . $match_type . "')";
			break;

			case 'domain_grey':
					$query_where = "(`value` $sql_match AND `type` = 'domain_grey')";
			break;

			
			case 'domain':
			case 'ip':
			case 'regex':
				if (($match_type == 'domain')
					&& ($grey_rslt = $wpdb->get_results("SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE `type` = 'domain_grey' AND `value` $sql_match")))
				{
					$query_where = "";
					$this->log_msg(__("Grey blacklist match: ignoring.", 'spam-karma'), 6);					
				}
				else
				{
					//$this->log_msg("BLAAAAA: $sql_match. ". "SELECT * FROM `" . SK_BLACKLIST_TABLE . "` WHERE `type` = 'domain_grey' AND `value` $sql_match", 7);					

					$query_where = "(`value` $sql_match AND (`type` = '" . $match_type . "_black' OR `type` = '" . $match_type . "_white'))";
				}
			break;
			
			case 'all':
				 $query_where = "`value` $sql_match";
			break;
			
			case 'kumo_seed':
			case 'rbl_server':
			default:
				$query_where = "`value` $sql_match AND `type` = '$match_type'";
			break;
		 }
	
		if (empty($query_where))
		{
				return false;
		}
		else
		{
			// FIXME: Use min_<var>s or lose them.  Leftover code?
			if ( isset( $min_score ) )
				$query_where .= " AND `score` > $min_score";
			if ( isset( $min_trust ) )
				$query_where .= " AND `trust` > $min_trust";

			$query = "SELECT * FROM `". SK_BLACKLIST_TABLE . "` WHERE $query_where ORDER BY `score` DESC";

			if ($limit)
				$query .= ' LIMIT ' . $limit;
			//echo $query;


			$blacklist_rows = $wpdb->get_results($query);
			if (mysql_error())
			{
				$this->log_msg(__("Failed to query blacklist: ", 'spam-karma') . "<em>$match_type</em> - <em>$match_value</em>. ". __("Query: ", 'spam-karma') . $query, 8, true);
				return false;
			}
			return $blacklist_rows;
		}
	}
	
	function get_list($type, $limit = 0)
	{
		global $wpdb;
		$query = "SELECT * FROM `". SK_BLACKLIST_TABLE. "` WHERE `type` = '$type'";
		if ($limit)
			$query .= " LIMIT $limit";
		$list = $wpdb->get_results($query);
		if (mysql_error())
		{
			$this->log_msg(__("get_list: Failed to get blacklist entries of type: ", 'spam-karma') . "<em>$type</em>. " . __("Query: ", 'spam-karma'). $query, 8, true);
			return false;
		}
	
		return ($list);
	}
	
	function increment_used ($ids)
	{
		global $wpdb;
		$str2 = $str = "(";
		foreach($ids as $id => $val_array)
		{
			$str .= $id . ", ";
			$str2 .= $id . " = " . $val_array['value'] . " [x". $val_array['used'] ."], ";
		}
		$str = substr($str, 0, strlen($str) - 2) . ")";
		$str2 = substr($str2, 0, strlen($str2) - 2) . ")";
		
		$query = "UPDATE `". SK_BLACKLIST_TABLE . "` SET `used_count` = `used_count` + 1, `last_used` = NOW() WHERE `id` IN $str";
		$wpdb->query($query);
		if (mysql_error())
			$this->log_msg(__("Failed to update blacklist used count.", 'spam-karma') . "</br>" . __("Query: ", 'spam-karma') . $query, 8, true);
		
		return $str2;
	}

	function downgrade_entries ($ids)
	{
		global $wpdb;
		$str2 = $str = "(";
		foreach($ids as $id => $val_array)
		{
			$str .= $id . ", ";
			$str2 .= $id . " = " . $val_array['value'] . " [x". $val_array['used'] ."], ";
		}
		$str = substr($str, 0, strlen($str) - 2) . ")";
		$str2 = substr($str2, 0, strlen($str2) - 2) . ")";
		
		$query = "UPDATE `". SK_BLACKLIST_TABLE . "` SET `score` = 0, `last_used` = NOW() WHERE `id` IN $str";
		$wpdb->query($query);
		if (mysql_error())
			$this->log_msg(__("Failed to downgrade blacklist scores.", 'spam-karma') . "</br> " . __("Query: ", 'spam-karma') . $query, 8, true);
		
		return $str2;
	}

	function log_msg($msg, $level = 0, $mysql = false)
	{
		global $sk_log;
		if ($mysql)
			$sk_log->log_msg_mysql($msg, $level, 0, "blacklist");
		else
			$sk_log->log_msg($msg, $level, 0, "blacklist");
	}

}

?>

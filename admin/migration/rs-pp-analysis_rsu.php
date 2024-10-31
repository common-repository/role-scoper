<?php
class RS_PP_Analysis {
	var $import_types = array();
	var $compat = array();
	//var $num_analyzed = array();
	
	var $all_post_ids = array();
	var $tt_id_by_taxonomy = array();
	
	var $rs_role_caps = array();
	var $wp_role_caps = array();
	
	var $post_caps = array();
	var $post_general_caps = array();
	var $all_pattern_role_caps = array();
	var $post_type_obj;
	
	var $rs_default_options = array();
	var $rs_default_otype_options = array();
	var $rs_options_netwide = array();
	var $rs_net_options = array();
	var $rs_blog_options = array();
	var $rs_forbidden_taxonomies = array();
	var $rs_default_disable_taxonomies = array();
	
	function __construct() {
		$this->import_types = array( 'sites' => __('Sites', 'ppi'), 'groups' => __('Groups', 'ppi'), 'group_members' => __('Group Members', 'ppi'), 'site_roles' => __('General Roles', 'ppi'), 'item_roles' => __('Term / Object Roles', 'ppi'), 'restrictions' => __('Restrictions', 'ppi'), 'options' => __('Options', 'ppi') );
	
		wp_enqueue_style( 'plugin-install' );
		wp_enqueue_script( 'plugin-install' );
		add_thickbox();
	}
	
	function inc( &$arr, $compat ) {
		if ( ! isset($arr[$compat]) )
			$arr[$compat] = 1;
		else
			$arr[$compat] = $arr[$compat] + 1;
	}
	
	function analyze() {
		global $wpdb, $blog_id;
	
		$compat_captions = array( 
			'core' => 		 __( 'Auto-importable with PublishPress Permissions (free download)', 'scoper' ),
			'core-manual' => __( 'Manually configurable with Permissions', 'scoper' ),
			'core-api' => 	 __( 'Require use of Permissions API', 'scoper' ),
			'pro' => 		 __( 'Auto-importable with Permissions Pro', 'scoper' ),
			'pro-manual' =>  __( 'Manually configurable with Permissions Pro', 'scoper' ),
			'pro-api' => 	 __( 'Require use of Permissions Pro API', 'scoper' ),
			'consult' => 	 __( 'Not supported by PublishPress Permissions. Paid consulting may be available', 'scoper' ),
		);
		
		?>

        <div id="rs_pp_migration_preview_container">
			<?php
			$img_url = plugins_url( '', SCOPER_FILE ) . '/admin/migration/images/';
			?>
			<img class="pp-logo pp-preview-step-1" src="<?php echo $img_url;?>pp-message.png" style="float:right;" />

            <div id="rs_pp_migration_preview_wrapper" class="rs-upgrade">

                <h2 class="rs-migration-heading">
                    <?php
                    printf(
                        __('%1$sStep 1%2$s. Migration preview', 'scoper'),
                        '<span>',
                        '</span>'
                    );
                    ?>
                </h2>

                <div id="rs_pp_migration_preview">

                <?php

                $this->load_role_defs();

                $this->all_post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type NOT IN ('revision', 'attachment') AND post_status NOT IN ('auto_draft')" );

                $results = $wpdb->get_results( "SELECT * FROM $wpdb->term_taxonomy" );
                foreach( $results as $row ) {
                    if ( ! isset( $this->tt_ids_by_taxonomy[$row->taxonomy] ) )
                        $this->tt_ids_by_taxonomy[$row->taxonomy] = array();

                    $this->tt_ids_by_taxonomy[$row->taxonomy][$row->term_id] = $row->term_taxonomy_id;
                }

                if ( MULTISITE && ( 1 === intval($blog_id) ) ) {
                    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id" );
                    $orig_blog_id = $blog_id;
                } else
                    $blog_ids = array( '1' );

                foreach ( $blog_ids as $id ) {
                    if ( count($blog_ids) > 1 ) {
                        switch_to_blog( $id );

                        rsu_rs_db_config();
                        if ( ! $wpdb->get_results( "SHOW TABLES LIKE '$wpdb->user2role2object_rs'" ) ) {
                            continue;	// RS tables were never created for this site, so skip it
                        }

                        //$this->num_analyzed['sites']++;
                        $bloginfo = get_blog_details();
                        ?>
                        <h3><?php printf( __( 'Site %1$s: %2$s' ), $id, $bloginfo->blogname );?></h3>
                        <?php
                    }

                    $this->compat = array_fill_keys( array( 'core', 'core-manual', 'core-api', 'pro', 'pro-manual', 'pro-api', 'consult' ), array() );
                    $this->rs_groups();
                    $this->rs_site_roles();
                    $this->rs_restrictions();
                    $this->rs_item_roles();
                    $this->rs_options();

                    foreach( array_keys( $this->compat ) as $ctype ) :?>
                        <?php if ( $this->compat[$ctype] ) :
                            $style = ( count($this->compat[$ctype]) > 10 ) ? ' style="display:none;"' : '';
                        ?>
                            <div class="rsu-issue"><h4><?php printf( __( '%2$s:', 'scoper' ), count($this->compat[$ctype]), $compat_captions[$ctype] );?></h4>
                            <ul<?php echo $style;?>>
                            <?php foreach( $this->compat[$ctype] as $caption ) :?>
                                <li><?php echo $caption;?></li>
                            <?php endforeach; ?>
                            </ul>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; /*compatibility type*/
                }

                if ( count($blog_ids) > 1 )
                    switch_to_blog( $orig_blog_id );

                ?>
					<div class="rs-migration-note">
					<?php printf( __( '%sNote:%s the free edition of PublishPress Permissions now supports custom editing permissions for your selected posts, categories and taxonomies!', 'scoper' ), '<strong>', '</strong>', '<a href="#pp-list-end">', '</a>' );?>
					</div>
				</div>
            </div>
            <div id="rs_pp_migration_links_wrapper" class="rs-upgrade">
                <?php
                $img_url = plugins_url( '', SCOPER_FILE ) . '/admin/migration/images/';
                $lang_id = 'scoper';
                ?>

                <h2 class="rs-migration-heading">
                    <?php
                    printf(
                        __('%1$sStep 2%2$s. Install PublishPress Permissions', 'scoper'),
                        '<span>',
                        '</span>'
                    );
                    ?>
                </h2>

                <div class="rs-dtable">
                    <p class="rs-nmt">
                        <img class="pp-logo" src="<?php echo $img_url;?>pp-logo.png" />
                        <?php echo __('PublishPress Permissions is the plugin for advanced WordPress permissions.', 'scoper'); ?>
                    </p>
                    <p>
                        <?php echo __('PublishPress Permissions allow you to assign supplemental roles and exceptions for custom post types, post-specific control for any user, group or WordPress role, control viewing access to specific categories and more.', 'scoper'); ?>
                    </p>
                    <p>
                        <?php
                        printf(
                            __('%1$sInstall PublishPress Permissions%2$s', 'scoper'),
                            '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=press-permit-core') . '" target="_blank" class="button rs-default-button">',
                            '</a>',
                            '<br class="rs-dm"><span class="rs-step-button rs-mlr-s">',
                            '</span><br class="rs-dm">',
                            '<a href="https://publishpress.com/presspermit/" target="_blank" class="button button-primary rs-pro-button">',
                            '</a>'
                        );
                        ?>
                        <br class="rs-dm">
                        <span class="rs-step-button rs-mlr-s">
                            <?php echo __('or', 'scoper'); ?>
                        </span>
                        <br class="rs-dm">
                        <?php printf(
                            __('%1$sBuy PublishPress Permissions Pro%2$s', 'scoper'),
                            '<a href="https://publishpress.com/presspermit/" target="_blank" class="button button-primary rs-pro-button">',
                            '</a>'
                        );
                        ?>
					</p>
					<p>
					<?php printf( __( 'After installation, navigate to Permissions > Settings > Import.', 'scoper' ), '<a href="#pp-list-end">', '</a>' );?>
					</p>
                </div>


                <div class="rs-migration-pp-links">
                    <a href="https://publishpress.com/presspermit/" target="_blank"><?php _e('Overview', $lang_id);?></a>
                    <span>&bull;</span>
                    <a href="https://publishpress.com/knowledge-base/permissions-start/" target="_blank"><?php _e('Documentation', $lang_id);?></a>
                    <span>&bull;</span>
                    <a href="http://publishpress.com/contact/" target="_blank"><?php _e('Pre-Sale Questions', $lang_id);?></a>
                </div>

            </div>

            <a name="pp-list-end" />
        </div>
		<?php
	}

	function rs_groups() {
		global $wpdb;
	
		// if groups were set to netwide, sites may not have their own RS groups/members tables
		if ( ! $wpdb->get_results( "SHOW TABLES LIKE '$wpdb->groups_rs'" ) || ! $wpdb->get_results( "SHOW TABLES LIKE '$wpdb->user2group_rs'" ) )
			return false;

		$rs_groups = $wpdb->get_results( "SELECT $wpdb->groups_id_col as rs_group_id, $wpdb->groups_name_col AS group_name, $wpdb->groups_descript_col AS group_description, $wpdb->groups_meta_id_col AS group_meta_id FROM $wpdb->groups_rs ORDER BY $wpdb->groups_name_col", OBJECT_K );

		$group_members = array();
		
		foreach( $rs_groups as $rs_group ) {
			if ( 0 === strpos( $rs_group->group_meta_id, 'wp_role_' ) ) {
				$metagroup_type = 'wp_role';

			} elseif ( 'wp_anon' == $rs_group->group_meta_id ) {
				$metagroup_type = 'wp_role';
			}
			
			if ( empty($metagroup_type) || ( $metagroup_type != 'wp_role' ) ) {	// RS stores WP role group membership alongside RS role assignments, but PP activation has already inserted equivalent records in pp_group_members
				$group_members['consult'] = 0;
				
				$_members = $wpdb->get_results( "SELECT $wpdb->user2group_uid_col as user_id, $wpdb->user2group_status_col AS status FROM $wpdb->user2group_rs WHERE $wpdb->user2group_gid_col = '$rs_group->rs_group_id'" );

				foreach( $_members as $row ) {
					if ( 'active' == $row->status ) {
						$this->inc( $group_members, 'core' );
					} else {
						$this->inc( $group_members, 'consult' );
					}
				}
				
				if ( ! empty( $group_members['consult'] ) )
					$this->compat['consult'][]= sprintf( _n( 'Group "%1$s": %2$d membership requests/recommendation', 'Group "%1$s": %2$d membership requests/recommendations', $group_members['consult'], 'scoper' ), $rs_group->group_name, $group_members['consult'] );
			}
		}
		
		if ( $rs_groups ) {
			$this->compat['core'][]= sprintf( _n( '%s Role Group', '%s Role Groups', count( $rs_groups ), 'scoper' ), count( $rs_groups ) );
			
			if ( ! empty( $group_members['core'] ) )
				$this->compat['core'][]= sprintf( _n( '%s Group Member', '%s Group Members', $group_members['core'], 'scoper' ), $group_members['core'] );
		}
	}

	function rs_site_roles() {
		global $wpdb;
		
		remove_filter( 'options_rs', 'scoper_apply_constants', 99 );
		remove_filter( 'site_options_rs', 'scoper_apply_constants', 99 );

		foreach( array( 'forbidden_taxonomies_rs', 'default_disable_taxonomies_rs', 'default_options_rs', 'default_otype_options_rs', 'options_rs' ) as $hook ) {
			if ( has_filter( $hook ) )
				$this->compat['core-api'][]= sprintf( __( 'Third party function hooked to filter %s', 'scoper' ), $hook );
		}
		
		foreach( array( 'options_sitewide_rs', 'site_options_rs' ) as $hook ) {
			if ( has_filter( $hook ) )
				$this->compat['pro-api'][]= sprintf( __( 'Third party function hooked to filter %s', 'scoper' ), $hook );
		}
		
		$now_date = current_time( 'mysql' );
		$results = $wpdb->get_results( "SELECT assignment_id AS source_id, role_name, user_id, group_id, assigner_id, date_limited, content_date_limited FROM $wpdb->user2role2object_rs WHERE role_type = 'rs' AND scope = 'blog' AND ( date_limited = '0' OR end_date_gmt < '$now_date' )", OBJECT_K );
		
		$cdate_count = 0;
		$date_lim_count = 0;
		$customized_roles = array();
		$manual_roles = array();
		$unsupported_roles = array();
		$ok_role_count = 0;
		
		foreach( $results as $row ) {
			if ( $row->content_date_limited ) {
				$cdate_count++;
				continue;
			}
			
			if ( $row->date_limited ) {
				$date_lim_count++;
				continue;
			}
			
			$is_unsupported = false;
			$is_manual = false;
			$is_customized = false;
			
			$arr = explode( '_', $row->role_name );

			if ( 0 === strpos( $row->role_name, 'private_' ) ) {
				$item_type = $arr[1];
				$item_status = 'private';
				unset( $arr[0] );
				unset( $arr[1] );
				$role_name = implode( '_', $arr );
			} else {
				$item_type = $arr[0];
				$item_status = '';
				unset( $arr[0] );
				$role_name = implode( '_', $arr );
			}
			
			if ( post_type_exists( $item_type ) ) {
				$item_source = 'post';
			
				if ( $role_name == 'reader' )
					$role_name = 'subscriber';
				
				$pp_pattern_role = $role_name;
				
				$pp_role_name = "{$role_name}:post:{$item_type}";
				if ( $item_status )
					$pp_role_name .= ":post_status:{$item_status}";
					
				if ( $type_obj = get_post_type_object( $item_type ) ) {
					if ( strpos( $row->role_name, '_associate' ) == ( strlen( $row->role_name - 10 ) ) ) {
						$is_manual = true;
					} elseif ( ! isset( $this->rs_role_caps[ 'rs_' . $row->role_name ] ) ) {
						$is_unsupported = true;
					} else {
						$pattern_role_caps = array();
						$type_caps = (array) $type_obj->cap;
						$rs_role_caps = $this->rs_role_caps[ 'rs_' . $row->role_name ];
						
						foreach( array_keys( $rs_role_caps ) as $cap_name ) {
							if ( in_array( $cap_name, $this->post_general_caps ) ) {
								$pattern_role_caps [$cap_name] = true;
								continue;
							}
								
							if ( $prop = array_search( $cap_name, $type_caps ) ) {
								if ( isset( $this->post_type_obj->cap->$prop ) )
									$pattern_role_caps [$this->post_type_obj->cap->$prop] = true;
							}
						}
						
						if ( ! isset( $this->wp_role_caps[$pp_pattern_role] ) ) {  // default pattern role does not exist (WP role must have been deleted).  PP will use default caps so this may work, but flag it anyway.
							$is_customized = true;
						
						} elseif ( $missing_caps = array_diff_key( $pattern_role_caps, $this->wp_role_caps[$pp_pattern_role] ) ) {	// WP pattern role is missing some caps which are in corresponding type-specific RS role def
							if ($missing_caps = array_diff_key($missing_caps, array_fill_keys(['read', 'read_private_pages', 'read_private_posts'], true))) {
								$is_customized = true;
							}
						
						} elseif ( $extra_caps = array_diff_key( array_intersect_key( $this->wp_role_caps[$pp_pattern_role], $this->all_pattern_role_caps ), $pattern_role_caps ) ) {  // WP pattern role has additional post-related caps not contained in corresponding type-specific RS role def
							$is_customized = true;
						}
					}
				}

			} elseif ( taxonomy_exists( $item_type ) ) {
				if ( strpos( $row->role_name, '_assigner' ) == ( strlen( $row->role_name - 9 ) ) ) {
					$is_manual = true;
				} elseif ( 'manager' == $role_name ) {
					//$pp_role_name = "pp_{$item_type}_manager";
				} else {
					$is_unsupported = true;
				}
			} else {
				if ( false !== strpos( $row->role_name, 'ngg_' ) )
					$is_unsupported = true;
				else
					continue; 	// ignore role assignment for disabled post types / taxonomies
			}
			
			if ( $is_unsupported ) {
				if ( isset($unsupported_roles[$row->role_name]) )
					$unsupported_roles[$row->role_name]++;
				else
					$unsupported_roles[$row->role_name] = 1;
				
				continue;
			}
			
			if ( $is_manual ) {
				if ( isset($manual_roles[$row->role_name]) )
					$manual_roles[$row->role_name]++;
				else
					$manual_roles[$row->role_name] = 1;
				
				continue;
			}
			
			if ( $is_customized ) {
				if ( isset($customized_roles[$row->role_name]) )
					$customized_roles[$row->role_name]++;
				else
					$customized_roles[$row->role_name] = 1;
				
				continue;
			}
			
			$ok_role_count++;
		}
		
		if ( $ok_role_count )
			$this->compat['core'][]= sprintf( _n( '%s General Role', '%s General Roles', $ok_role_count, 'scoper' ), $ok_role_count );

		if ( $cdate_count )
			$this->compat['consult'][]= sprintf( _n( '%s General Role with content date limits', '%s General Roles with content date limits', $cdate_count, 'scoper' ), $cdate_count );

		if ( $date_lim_count )
			$this->compat['pro-manual'][]= sprintf( _n( '%s General Role with role date limits', '%s General Roles with role date limits', $date_lim_count, 'scoper' ), $date_lim_count );
			
		if ( $manual_roles ) {
			foreach( $manual_roles as $role_name => $role_count )
				$this->compat['pro-manual'][]= sprintf( _n( '%1$s General Role of "%2$s" (n/a role)', '%1$s General Roles of "%2$s" (n/a role)', $role_count, 'scoper' ), $role_count, $role_name );
		}

		if ( $customized_roles ) {
			foreach( $customized_roles as $role_name => $role_count )
				$this->compat['core-manual'][]= sprintf( _n( '%1$s General Role of "%2$s" (customized RS or WP role caps)', '%1$s General Roles of "%2$s" (customized RS or WP role caps)', $role_count, 'scoper' ), $role_count, ucwords( str_replace( '_', ' ', $role_name ) ) );
		}
		
		if ( $unsupported_roles ) {
			foreach( $unsupported_roles as $role_name => $role_count )
				$this->compat['consult'][]= sprintf( _n( '%1$s General Role of "%2$s" (unsupported role)', '%1$s General Roles of "%2$s" (unsupported role)', $role_count, 'scoper' ), $role_count, $role_name );
		}
	}
	
	function rs_restrictions() {
		global $wpdb, $pp, $wp_roles;
		
		$post_types = get_post_types( array( 'public' => true ), 'object' );
		
		/*
		post_reader => Subscriber (and any other role with read but not read_private or edit_posts)
		private_post_reader => Subscriber (and any other roles that have read_private but not edit_posts)
		post_contributor => Contributor (and any other roles that have edit_posts but not edit_published_posts)
		page_contributor => (any role that has edit_pages but not edit_published_pages or edit_others_pages)
		post_author => Author (and any other roles that have edit_published but not edit_others)
		*_author => 
		*_revisor => Revisor (any role that has edit_posts and edit_others but not edit_published)
		*_editor => Editor (any role that has edit_posts, edit_others and edit_published)
		*/
		$wp_role_restrictions = array();
		foreach ( $post_types as $post_type => $type_obj ) {
			$cap = $type_obj->cap;
			
			if ( ! isset( $cap->edit_posts ) )
				continue;
			
			if ( empty($cap->edit_published_posts) )
				$cap->edit_published_posts = str_replace( 'edit_', 'edit_published', $cap->edit_posts );
				
			if ( empty($cap->edit_others_posts) )
				$cap->edit_others_posts = str_replace( 'edit_', 'edit_others', $cap->edit_posts );
				
			if ( empty($cap->read_private_posts) )
				$cap->read_private_posts = str_replace( 'edit_', 'read_private_', $cap->edit_posts );
		
			foreach( array( 'subscriber', 'contributor', 'author', 'editor', 'revisor' ) AS $role_name ) {
				if ( ! isset( $wp_roles->role_objects[$role_name] ) )
					continue;

				$role_caps = array_intersect( $wp_roles->role_objects[$role_name]->capabilities, array( 1, "1", true ) );
				
				$exemption_caps = array( 'activate_plugins', 'administer_content', 'pp_administer_content' );
				if ( defined('SCOPER_CONTENT_ADMIN_CAP') )
					$exemption_caps[]= constant('SCOPER_CONTENT_ADMIN_CAP');
				
				if ( array_intersect_key( $role_caps, array_fill_keys( $exemption_caps, true ) ) )
					continue;
				
				if ( ! empty( $role_caps[ 'read' ] ) && empty( $role_caps[ $cap->read_private_posts ] ) && empty( $role_caps[ $cap->edit_posts ] ) ) {
					if ( ! isset( $wp_role_restrictions["{$post_type}_reader"] ) ) { $wp_role_restrictions["{$post_type}_reader"] = array(); }
					$wp_role_restrictions["{$post_type}_reader"] []= $role_name;
				}
				
				if ( ! empty( $role_caps[ $cap->read_private_posts ] ) && empty( $role_caps[ $cap->edit_posts ] ) ) {
					if ( ! isset( $wp_role_restrictions["private_{$post_type}_reader"] ) ) { $wp_role_restrictions["private_{$post_type}_reader"] = array(); }
					$wp_role_restrictions["private_{$post_type}_reader"] []= $role_name;
				}
				
				if ( ! empty( $role_caps[ $cap->edit_posts ] ) && empty( $role_caps[ $cap->edit_published_posts ] ) && empty( $role_caps[ $cap->edit_others_posts ] ) ) {
					if ( ! isset( $wp_role_restrictions["{$post_type}_contributor"] ) ) { $wp_role_restrictions["{$post_type}_contributor"] = array(); }
					$wp_role_restrictions["{$post_type}_contributor"] []= $role_name;
				}
			
				if ( ! empty( $role_caps[ $cap->edit_published_posts ] ) && empty( $role_caps[ $cap->edit_others_posts ] ) ) {
					if ( ! isset( $wp_role_restrictions["{$post_type}_author"] ) ) { $wp_role_restrictions["{$post_type}_author"] = array(); }
					$wp_role_restrictions["{$post_type}_author"] []= $role_name;
				}

				if ( ! empty( $role_caps[ $cap->edit_posts ] ) && ! empty( $role_caps[ $cap->edit_others_posts ] ) && empty( $role_caps[ $cap->edit_published_posts ] ) ) {
					if ( ! isset( $wp_role_restrictions["{$post_type}_revisor"] ) ) { $wp_role_restrictions["{$post_type}_revisor"] = array(); }
					$wp_role_restrictions["{$post_type}_revisor"] []= $role_name;
				}
				
				if ( ! empty( $role_caps[ $cap->edit_posts ] ) && ! empty( $role_caps[ $cap->edit_others_posts ] ) && ! empty( $role_caps[ $cap->edit_published_posts ] ) ) {
					if ( ! isset( $wp_role_restrictions["{$post_type}_editor"] ) ) { $wp_role_restrictions["{$post_type}_editor"] = array(); }
					$wp_role_restrictions["{$post_type}_editor"] []= $role_name;
				}
			}
		}

		// === Restrictions and Unrestrictions (direct-assigned) ===
		$rs_restrictions = array();
		
		$default_restrictions = $wpdb->get_results( "SELECT requirement_id AS source_id, role_name, require_for, inherited_from, topic, src_or_tx_name, max_scope FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND ( topic = 'term' OR ( topic = 'object' AND src_or_tx_name = 'post' ) ) AND max_scope = topic AND obj_or_term_id = '0'", OBJECT_K );
		$def_restrictions_for = array();
		foreach( $default_restrictions as $row ) {
			$key = $row->role_name . '~' . $row->topic . '~' . $row->src_or_tx_name;
			$def_restrictions_for[$key] = true;
		}
		
		$unsupported_roles = array();
		$core_count = 0;
		$edit_count = 0;
		$pro_count = 0;
		$manual_count = 0;
		$custom_roles = array();
		
		$results = $wpdb->get_results( "SELECT requirement_id AS source_id, role_name, obj_or_term_id AS item_id, require_for, inherited_from, topic, src_or_tx_name, max_scope FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND obj_or_term_id > 0 AND ( topic = 'term' OR ( topic = 'object' AND src_or_tx_name = 'post' ) )", OBJECT_K );

		foreach( $results as $row ) {			
			if ( empty( $wp_role_restrictions[$row->role_name] ) ) {
				if ( isset($unsupported_roles[$row->role_name]) )
					$unsupported_roles[$row->role_name]++;
				else
					$unsupported_roles[$row->role_name] = 1;
				
				continue;
			}
			
			if ( ( 'object' == $row->topic ) AND ! in_array( $row->item_id, $this->all_post_ids ) )
				continue; // ignore orphaned object restrictions
				
			if ( 'term' == $row->topic ) {
				if ( ! isset( $this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id] ) )
					continue;
					
				// convert term_id to term_taxonomy_id
				$row->item_id = $this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id];
			}
			
			$key = $row->role_name . '~' . $row->topic . '~' . $row->src_or_tx_name;
			
			if ( $row->max_scope != $row->topic ) {
				// disregard unrestrictions which do not have a corresponding default restriction active
				if ( ! isset( $def_restrictions_for[$key] ) )
					continue;
			} else {
				// disregard restrictions which DO have a corresponding default restriction active (RS config UI treats them as non-existant)
				if ( isset( $def_restrictions_for[$key] ) )
					continue;
			}
			
			if ( ! $data = $this->get_exception_fields( $row ) ) {  // determines mod_type (exclude or include) based on topic and max_scope
				if ( isset($unsupported_roles[$row->role_name]) )
					$unsupported_roles[$row->role_name]++;
				else
					$unsupported_roles[$row->role_name] = 1;
				
				continue;
			}
			
			if ( $data['for_item_type'] && ! post_type_exists($data['for_item_type']) && ! taxonomy_exists($data['for_item_type']) )
				continue; // disregard restrictions for disabled post type / taxonomy
			
			if ( $data['via_item_type'] && ! post_type_exists($data['via_item_type']) && ! taxonomy_exists($data['via_item_type']) )
				continue; // disregard restrictions for disabled post type / taxonomy
			
			$_role = (count( $wp_role_restrictions[$row->role_name] ) == 1) ? reset($wp_role_restrictions[$row->role_name]) : '';

			if ($_role == 'subscriber') {
				$core_count++;
			} elseif (in_array($_role, array('subscriber', 'contributor', 'author', 'revisor', 'editor'))) {
				$edit_count++;
			} elseif (!empty($wp_role_restrictions[$row->role_name])) {
				$manual_count++;
				$_custom = "Custom restrictions for $row->role_name (" . implode(', ', array_map('ucwords', $wp_role_restrictions[$row->role_name])) . ')';

				if (!in_array($_custom, $custom_roles)) {
					$custom_roles []= $_custom;
				}
			}
		}

		if ( $core_count )
			$this->compat['core'][]= sprintf( _n( '%s Reader Restriction', '%s Reader Restrictions', $core_count, 'scoper' ), $core_count );
		
		if ($edit_count)
			$this->compat['core'][]= sprintf( _n( '%s Editing Restriction', '%s Editing Restrictions', $edit_count, 'scoper' ), $edit_count );

		if ( $manual_count ) {
			$roles_csv = implode("<br /> ", $custom_roles);
			$this->compat['core-manual'][]= $roles_csv;
		}

		// This is giving false positives due to post type disable
		/*
		if ( $unsupported_roles ) {
			foreach( $unsupported_roles as $role_name => $role_count )
				$this->compat['consult'][]= sprintf( _n( '%1$s Restriction of "%2$s" (unsupported role definition)', '%1$s Restrictions of "%2$s" (unsupported role definition)', $role_count, 'scoper' ), $role_count, $role_name );
		}
		*/
		
		$unsupported_roles = array();
		$core_count = 0;
		$pro_count = 0;
		
		// Default Restrictions will be imported as Include Exceptions for corresponding WP Role group
		//$default_restrictions = array_diff_key( $default_restrictions, $imported_restrictions );
		foreach( $default_restrictions as $row ) {
			if ( empty( $wp_role_restrictions[$row->role_name] ) ) {
				if ( isset($unsupported_roles[$row->role_name]) )
					$unsupported_roles[$row->role_name]++;
				else
					$unsupported_roles[$row->role_name] = 1;
					
				continue;
			}
	
			if ( ! $data = $this->get_exception_fields( $row ) ) {
				if ( isset($unsupported_roles[$row->role_name]) )
					$unsupported_roles[$row->role_name]++;
				else
					$unsupported_roles[$row->role_name] = 1;
				
				continue;
			}
			
			if ( $data['for_item_type'] && ! post_type_exists($data['for_item_type']) && ! taxonomy_exists($data['for_item_type']) )
				continue; // disregard restrictions for disabled post type / taxonomy
			
			if ( $data['via_item_type'] && ! post_type_exists($data['via_item_type']) && ! taxonomy_exists($data['via_item_type']) )
				continue; // disregard restrictions for disabled post type / taxonomy
			
			if ( count( $wp_role_restrictions[$row->role_name] ) == 1 && ( reset($wp_role_restrictions[$row->role_name]) == 'subscriber' ) )
				$core_count++;
			else
				$pro_count++;
		}
		
		if ( $core_count )
			$this->compat['core'][]= sprintf( _n( '%s Default Reader Restriction', '%s Default Reader Restrictions', $core_count, 'scoper' ), $core_count );
		
		if ( $pro_count )
			$this->compat['core'][]= sprintf( _n( '%s Default Editing Restriction', '%s Default Editing Restrictions', $pro_count, 'scoper' ), $pro_count );
		
		if ( $unsupported_roles ) {
			foreach( $unsupported_roles as $role_name => $role_count )
				$this->compat['consult'][]= sprintf( _n( '%1$s Default Restriction of "%2$s" (unsupported role)', '%1$s Default Restrictions of "%2$s" (unsupported role)', $role_count, 'scoper' ), $role_count, $role_name );
		}
		// === end RS restrictions import ===
	}

	function rs_item_roles() {
		global $wpdb, $wp_roles;
		
		$unsupported_roles = array();
		$core_count = 0;
		$pro_count = 0;
		$group_mgr_count = 0;
		
		$results = $wpdb->get_results( "SELECT assignment_id AS source_id, role_name, obj_or_term_id AS item_id, assign_for, inherited_from, scope, src_or_tx_name, user_id, group_id, assigner_id FROM $wpdb->user2role2object_rs WHERE role_type = 'rs' AND scope IN ( 'term', 'object' ) AND date_limited = '0' AND content_date_limited = '0'", OBJECT_K );

		foreach( $results as $row ) {
			if ( 'group_manager' == $row->role_name ) {
				$group_mgr_count++;
				continue;
			}
		
			if ( ( 'object' == $row->scope ) && ( 'group' != $row->src_or_tx_name ) && ! in_array( $row->item_id, $this->all_post_ids ) )
				continue; // ignore orphaned object roles
				
			if ( 'term' == $row->scope ) {
				if ( ! isset( $this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id] ) )
					continue;
					
				// convert term_id to term_taxonomy_id
				$row->item_id = $this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id];
			}
		
			if ( ! $data = $this->get_exception_fields( $row ) ) {
				if ( isset($unsupported_roles[$row->role_name]) )
					$unsupported_roles[$row->role_name]++;
				else
					$unsupported_roles[$row->role_name] = 1;
				
				continue;
			}
			
			if ( $data['operation'] == 'read' )
				$core_count++;
			else
				$pro_count++;
		}
		
		if ( $core_count )
			$this->compat['core'][]= sprintf( _n( '%s Term / Object Reader Role', '%s Term / Object Reader Roles', $core_count, 'scoper' ), $core_count );
		
		if ( $pro_count )
			$this->compat['core'][]= sprintf( _n( '%s Term / Object Editing Role', '%s Term / Object Editing Roles', $pro_count, 'scoper' ), $pro_count );
		
		if ( $group_mgr_count )
			$this->compat['core'][]= sprintf( _n( '%s Group Administrator Assignment', '%s Group Administrator Assignments', $group_mgr_count, 'scoper' ), $group_mgr_count );
		
		if ( $unsupported_roles ) {
			foreach( $unsupported_roles as $role_name => $role_count )
				$this->compat['consult'][]= sprintf( _n( '%1$s Term / Object assignment of "%2$s" (unsupported role)', '%1$s Term / Object assignments of "%2$s" (unsupported role)', $role_count, 'scoper' ), $role_count, $role_name );
		}
	}
	
	function rs_options() {
		global $wpdb, $wp_roles;
		
		static $done;
		if ( empty($done) ) {
			remove_filter( 'options_rs', 'scoper_apply_constants', 99 );
			remove_filter( 'site_options_rs', 'scoper_apply_constants', 99 );
		
			foreach( array( 'forbidden_taxonomies_rs', 'default_disable_taxonomies_rs', 'default_options_rs', 'default_otype_options_rs', 'options_rs' ) as $hook ) {
				if ( has_filter( $hook ) ) {
					$this->compat['core-api'][]= sprintf( __( 'Third party function hooked to filter %s', 'scoper' ), $hook );
				}
			}
			
			foreach( array( 'options_sitewide_rs', 'site_options_rs' ) as $hook ) {
				if ( has_filter( $hook ) )
					$this->compat['pro-api'][]= sprintf( __( 'Third party function hooked to filter %s', 'scoper' ), $hook );
			}
				
			$this->rs_forbidden_taxonomies = array_fill_keys( array( 'post_status', 'following_users', 'unfollowing_users', 'following_usergroups', 'ef_editorial_meta' ), true );
			$this->rs_default_disable_taxonomies = array_fill_keys( array( 'link_category', 'post_tag', 'post_format', 'ngg_tag' ), true );
			
			$this->rs_default_options = $this->rs_default_options();
			$this->rs_default_otype_options = $this->rs_default_otype_options();

			$relevant_options = array(
				/*'define_usergroups',*/
				/*'user_role_caps',*/
				/*'disabled_role_caps',*/
				/*'no_frontend_admin',*/
				'strip_private_caption',
				'display_hints',
				'hide_non_editor_admin_divs',
				'role_admin_blogwide_editor_only',
				/*'feed_link_http_auth','rss_private_feed_mode','rss_nonprivate_feed_mode','feed_teaser'*/
				/*'rs_page_reader_role_objscope',
				'rs_page_author_role_objscope',
				'rs_post_reader_role_objscope',
				'rs_post_author_role_objscope',
				*/
				'lock_top_pages',
				'display_user_profile_groups',
				'display_user_profile_roles',
				'admin_others_attached_files',
				'admin_others_unattached_files',
				/*'remap_page_parents',
				'enforce_actual_page_depth',
				'remap_thru_excluded_page_parent',
				'remap_term_parents',
				'enforce_actual_term_depth',
				'remap_thru_excluded_term_parent',
				*/
				'limit_user_edit_by_level',
				'mu_sitewide_groups',
				/*'role_duration_limits',
				'role_content_date_limits',
				'filter_users_dropdown',*/
				'admin_nav_menu_filter_items',
				/*'require_moderate_comments_cap',*/
				'define_create_posts_cap',
				
				'use_teaser',
				'teaser_hide_private',
				'teaser_logged_only',
				'teaser_replace_content',
				'teaser_replace_content_anon',
				'teaser_prepend_content', 
				'teaser_prepend_content_anon', 
				'teaser_append_content', 
				'teaser_append_content_anon',
				'teaser_prepend_name', 
				'teaser_prepend_name_anon',
				'teaser_append_name', 
				'teaser_append_name_anon', 
				'teaser_replace_excerpt', 
				'teaser_replace_excerpt_anon', 
				'teaser_prepend_excerpt', 
				'teaser_prepend_excerpt_anon',
				'teaser_append_excerpt', 
				'teaser_append_excerpt_anon', 
			);
			
			if ( is_multisite() ) {
				$this->rs_options_netwide = apply_filters( 'options_sitewide_rs', $this->rs_default_options_netwide(), array( true ) );	// establishes which options are set network-wide
				
				if ( $options_sitewide_reviewed =  get_site_option( 'scoper_options_sitewide_reviewed' ) ) {
					$custom_options_sitewide = (array) get_site_option( 'scoper_options_sitewide' );

					$unreviewed_default_sitewide = array_diff( array_keys($this->rs_options_netwide), $options_sitewide_reviewed );

					$this->rs_options_netwide = array_fill_keys( array_merge( $custom_options_sitewide, $unreviewed_default_sitewide ), true );
				}
				
				if ( empty( $this->rs_options_netwide['file_filtering'] ) )
					$this->rs_options_netwide['file_filtering'] = true;	// file filtering option must be set site-wide (this DOES NOT set the option value itself)
				
				if ( empty( $this->rs_options_netwide['mu_sitewide_groups'] ) )
					$this->rs_options_netwide['mu_sitewide_groups'] = true;	// sitewide_groups option must be set site-wide!
				
				$this->rs_apply_custom_default_options();
				$this->rs_retrieve_options(true);
				
				$check_netwide_options = array_intersect_key( $this->rs_options_netwide, array_flip($relevant_options) );
				
				$customized_netwide_options = array();
				foreach( array_keys( $check_netwide_options ) as $opt_name ) {
					if ( isset($this->rs_default_otype_options[$opt_name]) ) {
						if ( isset($this->rs_net_options['scoper_' . $opt_name]) && $this->rs_default_otype_options[$opt_name] != $this->rs_net_options['scoper_' . $opt_name] ) {
							$this->compat['core-api'][]= sprintf( __( 'Customized network default for option array %s', 'scoper' ), $opt_name );
						}
					} elseif ( isset($this->rs_default_options[$opt_name]) ) {
						if ( isset($this->rs_net_options['scoper_' . $opt_name]) && $this->rs_default_options[$opt_name] != $this->rs_net_options['scoper_' . $opt_name] ) {
							$this->compat['core-api'][]= sprintf( __( 'Customized network default for option %s', 'scoper' ), $opt_name );
						}
					}
				}
			}
			
			$done = true;
		}
		$this->rs_retrieve_options(false);
		

		if( $this->rs_get_otype_option( 'do_teaser', 'post') ) {
			$this->compat['pro'][]= __( 'Pro Feature: Hidden Content Teaser', 'scoper' );

			$teaser_types = array();
			foreach( get_post_types( array('public' => true), 'names' ) as $post_type ) {
				if ( $this->rs_get_otype_option( 'use_teaser', 'post', $post_type ) )
					$teaser_types[]= $post_type;
			}
			
			$options = array( 'replace_content', 'replace_content_anon', 'prepend_content', 'prepend_content_anon', 'append_content', 'append_content_anon', 'prepend_name', 'prepend_name_anon', 'append_name', 'append_name_anon', 'replace_excerpt', 'replace_excerpt_anon', 'prepend_excerpt', 'prepend_excerpt_anon', 'append_excerpt', 'append_excerpt_anon' );
			foreach( $options as $opt ) {
				$first = true;
				
				foreach( $teaser_types as $type ) {
					if ( $first )
						$main_setting = $this->rs_get_otype_option( 'teaser_' . $opt, 'post', $type );
					else {
						if ( $main_setting != $this->rs_get_otype_option( 'teaser_' . $opt, 'post', $type ) ) {
							$this->compat['pro-api'][]= sprintf( __( 'Type-specific option requires use of plugin API : Teaser %s', 'scoper' ), ucwords( str_replace( '_', ' ', $opt ) ) );
							break;
						}
					}
					
					$first = false;
				}
			}
		}
			
		if( $arr = (array) $this->rs_get_option( 'default_private') ) {
			foreach ( $arr as $val ) {
				if ( $val ) {
					$this->compat['pro'][]= __( 'Pro Feature: Default Privacy', 'scoper' );
					break;
				}
			}
		}
			
		if( $this->rs_get_option( 'file_filtering') && ( ! defined( 'DISABLE_ATTACHMENT_FILTERING' ) || ! DISABLE_ATTACHMENT_FILTERING ) )
			$this->compat['pro'][]= __( 'Pro Feature: File Filtering', 'scoper' );
		
		if( $this->rs_get_option( 'group_requests') )
			$this->compat['consult'][]= __( 'Unsupported Option: Role Group Requests', 'scoper' );
		
		if( $this->rs_get_option( 'group_recommendations') )
			$this->compat['consult'][]= __( 'Unsupported Option: Role Group Recommendations', 'scoper' );
		
		if( $this->rs_get_option( 'feed_link_http_auth') )
			$this->compat['consult'][]= __( 'Unsupported Option: HTTP Authentication Request in RSS Feed Links', 'scoper' );
		
		if( $this->rs_get_option( 'role_content_date_limits') )
			$this->compat['consult'][]= __( 'Unsupported Option: Enable Content Date Limits for Roles', 'scoper' );
			
		if( $this->rs_get_option( 'role_duration_limits') )
			$this->compat['pro-manual'][]= __( 'Manual Configuration Required: Role Duration Limits', 'scoper' );
			
		if( $this->rs_get_option( 'group_recommendations') )
			$this->compat['pro-manual'][]= __( 'Option requires manual reconfig: Role Duration Limits', 'scoper' );
		
		$this->compat['pro'][]= __( 'Pro Feature: Media Library Filtering (based on parent post access)', 'scoper' );
		
		if ( defined( 'SCOPER_CONTENT_ADMIN_CAP' ) )
			$this->compat['core-manual'][]= __( 'Manual configuration: customized content administration capability', 'scoper' );
			
		if ( defined( 'SCOPER_USER_ADMIN_CAP' ) )
			$this->compat['core-manual'][]= __( 'Manual configuration: customized user administration capability', 'scoper' );
			
		if ( defined( 'SCOPER_OPTION_ADMIN_CAP' ) )
			$this->compat['core-manual'][]= __( 'Manual configuration: customized option administration capability', 'scoper' );
		
		if ( ! $this->rs_get_option( 'define_usergroups') )
			$this->compat['core-manual'][]= __( 'Manual Configuration: disable User Groups', 'scoper' );
		
		if ( ! $this->rs_get_option( 'enable_group_roles') )
			$this->compat['consult'][]= __( 'Unsupported Option: disable Group Roles', 'scoper' );
			
		if ( ! $this->rs_get_option( 'enable_user_roles') )
			$this->compat['consult'][]= __( 'Unsupported Option: disable User Roles', 'scoper' );
			
		if ( $arr = $this->rs_get_option( 'disabled_access_types') ) {
			foreach( $arr as $val ) {
				if ( $val ) {
					$this->compat['consult'][]= __( 'Unsupported Option: disabled Access Types', 'scoper' );
					break;
				}
			}
		}
		
		if ( $this->rs_get_option( 'no_frontend_admin') )
			$this->compat['core-api'][]= __( 'Option requires use of plugin API: Assume No Front-end Admin', 'scoper' );
		
		if ( $this->rs_get_otype_option( 'limit_object_editors', 'post', 'post' ) || $this->rs_get_otype_option( 'limit_object_editors', 'post', 'page' ) )
			$this->compat['consult'][]= __( 'Unsupported Option: Limit eligible users for Post-specific editing roles', 'scoper' );
		
		if ( ! $this->rs_get_otype_option( 'private_items_listable', 'post', 'page' ) )
			$this->compat['consult'][]= __( 'Unsupported Option: Omit private pages from listing even if logged user can read them', 'scoper' );
		
		if ( $this->rs_get_option( 'require_moderate_comments_cap' ) )
			$this->compat['consult'][]= __( 'Unsupported Option: Require moderate_comments capability', 'scoper' );
		
		if ( MULTISITE ) {
			if ( ! $this->rs_get_option( 'mu_sitewide_groups') )
				$this->compat['pro'][]= __( 'Pro Option: Network-wide role groups', 'scoper' );
		}
		
		if ( defined( 'SCOPER_OPTION_ADMIN_CAP' ) )
			$this->compat['core-manual'][]= __( 'Manual configuration: customized option administration capability', 'scoper' );
		
		/*
		$core_options = array( 
			'scoper_strip_private_caption',
			'scoper_display_hints',
			'scoper_display_user_profile_groups',
			'scoper_display_user_profile_roles',
			'scoper_define_create_posts_cap',
			'scoper_role_admin_blogwide_editor_only',
			'scoper_use_post_types',
			'scoper_use_taxonomies',
		);
		*/
		
		$this->compat['core'][]= __( 'Options: Post Type Usage, Taxonomy Usage, Private Caption, Create Posts Capability, Role Assignment Roles, Media Library, Page Structure (Top Level lock), User Profile Group/Role Display, Limit User Edit, Limited Editing Elements, Display Hints', 'scoper' );
	}
	
	function rs_default_options() {
		$def = array(
			'persistent_cache' => 1,
			'define_usergroups' => 1,
			'group_ajax' => 1,
			'group_requests' => 0,
			'group_recommendations' => 0,
			'enable_group_roles' => 1,
			'enable_user_roles' => 1,
			/*'rs_blog_roles' => 1, */
			'custom_user_blogcaps' => 0,
			'user_role_caps' => array(),	/* NOTE: "user" here does not refer to WP user account(s), but to the user of the plugin.  The option value adds capabilities to RS Role Definitions, and would have been better named "custom_role_caps"  */
			'disabled_role_caps' => array(),
			'disabled_access_types' => array(),
			'no_frontend_admin' => 0,
			'indicate_blended_roles' => 1,
			'version_update_notice' => 1,
			'version_check_minutes' => 30,
			'strip_private_caption' => 0,
			'display_hints' => 1,
			'hide_non_editor_admin_divs' => 1,
			'role_admin_blogwide_editor_only' => 1,
			'feed_link_http_auth' => 0,
			'rss_private_feed_mode' => 'title_only',
			'rss_nonprivate_feed_mode' => 'full_content',
			'feed_teaser' => "View the content of this <a href='%permalink%'>article</a>",
			'rs_page_reader_role_objscope' => 0,
			'rs_page_author_role_objscope' => 0,
			'rs_post_reader_role_objscope' => 0,
			'rs_post_author_role_objscope' => 0,
			'lock_top_pages' => 0,
			'display_user_profile_groups' => 0,
			'display_user_profile_roles' => 0,
			'user_role_assignment_csv' => 0,
			'admin_others_attached_files' => 0,
			'admin_others_unattached_files' => 0,
			'remap_page_parents' => 0,
			'enforce_actual_page_depth' => 1,
			'remap_thru_excluded_page_parent' => 0,
			'remap_term_parents' => 0,
			'enforce_actual_term_depth' => 1,
			'remap_thru_excluded_term_parent' => 0,
			'limit_user_edit_by_level' => 1,
			'file_filtering' => 0,
			'mu_sitewide_groups' => 1,  // version check code will set this to 0 for first-time execution of this version on mu installations that ran a previous RS version
			'role_duration_limits' => 1,
			'role_content_date_limits' => 1,
			'filter_users_dropdown' => 1,
			'auto_private' => 1,
			'admin_nav_menu_filter_items' => 0,
			'require_moderate_comments_cap' => 0,
			'define_create_posts_cap' => 0,
			'dismissals' => array(),
		);
		
		$post_types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
		foreach ( $post_types as $type )
			$def['use_post_types'][$type] = 1;
		
		foreach ( array_diff( get_taxonomies( array( 'public' => true ) ), $this->rs_forbidden_taxonomies ) as $taxonomy ) {
			if ( isset( $this->rs_default_disable_taxonomies[$taxonomy] ) )
				$def['use_taxonomies'][$taxonomy] = 0;
			else
				$def['use_taxonomies'][$taxonomy] = 1;
		}
		
		return $def;
	}

	function rs_default_otype_options() {
		$def = array();
		
		//------------------------ DEFAULT OBJECT TYPE OPTIONS ---------------------		
		// 	format for second key is {src_name}:{object_type}

		$post_types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );

		foreach ( $post_types as $type ) {
			$def['limit_object_editors']["post:{$type}"] = 0;
			$def['default_private']["post:{$type}"] = 0;
			$def['sync_private']["post:{$type}"] = 0;
			$def['restrictions_column']["post:{$type}"] = 1;
			$def['term_roles_column']["post:{$type}"] = 1;
			$def['object_roles_column']["post:{$type}"] = 1;
			
			$def['use_teaser'] ["post:{$type}"] = 1;  // use teaser (if enabled) for WP posts.  Note: Use integer because this option is multi-select.  Other valid setting is "excerpt"
			
			$def['teaser_hide_private']["post:{$type}"] = 0;
			$def['teaser_logged_only'] ["post:{$type}"] = 0;
			
			$def['teaser_replace_content']		["post:{$type}"] = "Sorry, this content requires additional permissions.  Please contact an administrator for help.";
			$def['teaser_replace_content_anon']	["post:{$type}"] = "Sorry, you don't have access to this content.  Please log in or contact a site administrator for help.";
			$def['teaser_prepend_content']		["post:{$type}"] = '';
			$def['teaser_prepend_content_anon']	["post:{$type}"] = '';
			$def['teaser_append_content']		["post:{$type}"] = '';
			$def['teaser_append_content_anon']	["post:{$type}"] = '';
			$def['teaser_prepend_name']			["post:{$type}"] = '(';
			$def['teaser_prepend_name_anon']	["post:{$type}"] = '(';
			$def['teaser_append_name']			["post:{$type}"] = ')*';
			$def['teaser_append_name_anon']		["post:{$type}"] = ')*';
			$def['teaser_replace_excerpt']		["post:{$type}"] = '';
			$def['teaser_replace_excerpt_anon']	["post:{$type}"] = '';
			$def['teaser_prepend_excerpt']		["post:{$type}"] = '';
			$def['teaser_prepend_excerpt_anon']	["post:{$type}"] = '';
			$def['teaser_append_excerpt']		["post:{$type}"] = "<br /><small>" . "note: This content requires a higher login level." . "</small>";
			$def['teaser_append_excerpt_anon']	["post:{$type}"] = "<br /><small>" . "note: This content requires site login." . "</small>";
			
			$def['use_object_roles']["post:{$type}"] = 1;
		} // end foreach post type
		
		$taxonomies = array_diff_key( get_taxonomies( array( 'public' => true ), 'object' ), $this->rs_forbidden_taxonomies );
		$taxonomies ['nav_menu']= get_taxonomy( 'nav_menu' );

		$post_types []= 'nav_menu_item';
		
		foreach ( $taxonomies as $taxonomy => $taxonomy_obj ) {
			$_object_types = (array) $taxonomy_obj->object_type;
			
			foreach( $_object_types as $object_type ) {
				if ( in_array( $object_type, $post_types ) )
					$def['use_term_roles']["post:{$object_type}"][$taxonomy] = 1;
			}
		}
		
		$def['do_teaser'] ['post'] = false;  					// don't enable teaser by default (separate per-type settings default to true)
		
		if( isset( $def['use_term_roles']['post:page']['category'] ) )
			$def['use_term_roles']['post:page']['category'] = 0;  // Wordpress core does not categorize pages by default
		
		$def['use_term_roles']['link:link']['link_category'] = 1; // Use Link Category roles by default

		$def['private_items_listable']['post:page'] = 1;
		
		$def['admin_css_ids'] ['post:post'] = 'password-span; slugdiv; edit-slug-box; authordiv; commentstatusdiv; trackbacksdiv; postcustom; revisionsdiv';	// this applied for all object types other than post
		$def['admin_css_ids'] ['post:page'] = 'password-span; pageslugdiv; edit-slug-box; pageauthordiv; pageparentdiv; pagecommentstatusdiv; pagecustomdiv; revisionsdiv';
		
		return $def;
	}
	
	function rs_default_options_netwide() {
		$def = array(
			'persistent_cache' => true,
			'define_usergroups' => true,
			'group_ajax' => true,
			'group_requests' => true,
			'group_recommendations' => true,
			'enable_group_roles' => true,
			'enable_user_roles' => true,
			'custom_user_blogcaps' => true,
			'no_frontend_admin' => true,
			'indicate_blended_roles' => true,
			'version_update_notice' => true,
			'version_check_minutes' => true,
			
			'rs_page_reader_role_objscope' => true,
			'rs_page_author_role_objscope' => true,
			'rs_post_reader_role_objscope' => true,
			'rs_post_author_role_objscope' => true,
			
			'display_user_profile_groups' => true,
			'display_user_profile_roles' => true,
			'user_role_assignment_csv' => true,
			'remap_page_parents' => false,
			'enforce_actual_page_depth' => true,
			'remap_thru_excluded_page_parent' => true,
			'remap_term_parents' => false,
			'enforce_actual_term_depth' => true,
			'remap_thru_excluded_term_parent' => true,
			'mu_sitewide_groups' => true,
			'file_filtering' => true,
			'file_filtering_regen_key' => true,
			'role_duration_limits' => true,
			'role_content_date_limits' => true,

			'disabled_access_types' => true,
			'use_taxonomies' => true,
			'use_post_types' => true,
			'use_term_roles' => true,
			'use_object_roles' => true,
			'disabled_role_caps' => true,
			'user_role_caps' => true,
			'filter_users_dropdown' => true,
			'restrictions_column' => true,
			'term_roles_column' => true,
			'object_roles_column' => true,
			
			'admin_nav_menu_filter_items' => true,
			'require_moderate_comments_cap' => true,
		);
		return $def;	
	}
	
	function rs_apply_custom_default_options() {
		global $wpdb;
		
		if ( $results = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE site_id = '$wpdb->siteid' AND meta_key LIKE 'scoper_default_%'" ) ) {
			foreach ( $results as $row ) {
				$option_basename = str_replace( 'scoper_default_', '', $row->meta_key );

				if ( ! empty( $this->rs_netwide_options[$option_basename] ) )
					continue;	// custom defaults are only for blog-specific options

				if( isset( $this->rs_default_options[$option_basename] ) )
					$this->rs_default_options[$option_basename] = maybe_unserialize( $row->meta_value );
					
				elseif( isset( $this->rs_default_otype_options[$option_basename] ) )
					$this->rs_default_otype_options[$option_basename] = maybe_unserialize( $row->meta_value );
			}
		}
	}
	
	function rs_retrieve_options( $sitewide = false ) {
		global $wpdb;
		
		if ( $sitewide ) {
			$this->rs_net_options = array();

			if ( $results = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE site_id = '$wpdb->siteid' AND meta_key LIKE 'scoper_%'" ) )
				foreach ( $results as $row )
					$this->rs_net_options[$row->meta_key] = $row->meta_value;

			$this->rs_net_options = $this->rs_net_options;
			
			foreach( array_keys($this->rs_net_options) as $key )
				$this->rs_net_options[$key] = maybe_unserialize( $this->rs_net_options[$key] );
			
			return $this->rs_net_options;

		} else {
			$this->rs_blog_options = array();
			
			if ( $results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'scoper_%'") )
				foreach ( $results as $row )
					$this->rs_blog_options[$row->option_name] = $row->option_value;
					
			$this->rs_blog_options = $this->rs_blog_options;
			
			foreach( array_keys($this->rs_blog_options) as $key )
				$this->rs_blog_options[$key] = maybe_unserialize( $this->rs_blog_options[$key] );
			
			return $this->rs_blog_options;
		}
	}
	
	function rs_get_option($option_basename, $sitewide = -1, $get_default = false) {
		if ( ! $get_default ) {
			// allow explicit selection of sitewide / non-sitewide scope for better performance and update security
			if ( -1 === $sitewide ) {
				$sitewide = isset( $this->rs_options_netwide ) && ! empty( $this->rs_options_netwide[$option_basename] );
			}
		
			if ( $sitewide ) {
				// this option is set site-wide
				if ( isset($this->rs_net_options["scoper_{$option_basename}"]) )
					$optval = $this->rs_net_options["scoper_{$option_basename}"];
				
			} else {
				if ( isset($this->rs_blog_options["scoper_$option_basename"]) )
					$optval = $this->rs_blog_options["scoper_$option_basename"];
			}
		}
		
		if ( ! isset( $optval ) ) {
			if ( ! empty($this->rs_default_options) && ! empty( $this->rs_default_options[$option_basename] ) )
				$optval = $this->rs_default_options[$option_basename];
				
			if ( ! isset($optval) ) {
				if ( isset( $this->rs_default_otype_options[$option_basename] ) )
					return $this->rs_default_otype_options[$option_basename];
			}
		}

		if ( isset($optval) )
			$optval = maybe_unserialize($optval);
		else
			$optval = '';
			
		// merge defaults into stored option array

		if ( 'use_post_types' == $option_basename ) {
			static $default_post_types;
			if ( empty($default_post_types) || ! did_action('init') ) {
				$default_post_types = array();
				
				foreach ( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) as $type )
					$default_post_types[$type] = 1;
			}
			
			$optval = array_merge( $default_post_types, (array) $optval );

		} elseif ( 'use_taxonomies' == $option_basename ) {	
			static $default_taxonomies;
			if ( empty($default_taxonomies) || ! did_action('init') ) {
				$default_taxonomies = array();
				
				$taxonomies = get_taxonomies( array( 'public' => true ) );
				$taxonomies[] = 'nav_menu';

				foreach ( $taxonomies as $taxonomy )
					$default_taxonomies[$taxonomy] = ( isset( $this->rs_default_disable_taxonomies[$taxonomy] ) ) ? 0 : 1;
			}
			
			$optval = array_diff_key( array_merge( $default_taxonomies, (array) $optval ), $this->rs_forbidden_taxonomies );  // remove forbidden taxonomies, even if previously stored
		} elseif ( 'use_term_roles' == $option_basename ) {
			if ( $optval ) {
				foreach( array_keys($optval) as $key ) {
					$optval[$key] = array_diff_key( $optval[$key], $this->rs_forbidden_taxonomies ); // remove forbidden taxonomies, even if previously stored
				}
			}
		}
		
		return $optval;
	}

	function rs_get_otype_option( $option_main_key, $src_name, $object_type = '', $access_name = '')  {
		static $otype_options;

		// make sure we indicate object roles disabled if object type usage is completely disabled
		if ( 'use_object_roles' == $option_main_key ) {
			if ( ( 'post' == $src_name ) && $object_type ) {
				$use_object_types = $this->rs_get_option( 'use_post_types' );
				if ( ( ! empty($use_object_types) ) && empty( $use_object_types[$object_type] ) )	// since default is to enable all object types, don't interfere if no use_object_types option is stored
					return false;
			}
		}
		
		$key = "$option_main_key,$src_name,$object_type,$access_name";

		if ( empty($otype_options) )
			$otype_options = array();
		elseif ( isset($otype_options[$key]) )
			return $otype_options[$key];

		$stored_option = $this->rs_get_option($option_main_key);

		// RS stores all portions of the otype option array together, but blending is needed because RS Extensions or other plugins can filter the default otype options array for specific taxonomies / object types
		$optval = $this->awp_blend_option_array( 'scoper_', $option_main_key, $this->rs_default_otype_options, 1, $stored_option );
		
		// note: access_name-specific entries are not valid for most otype options (but possibly for teaser text front vs. rss)
		if ( isset ( $optval[$src_name] ) )
			$retval = $optval[$src_name];
		
		if ( $object_type && isset( $optval["$src_name:$object_type"] ) )
			$retval = $optval["$src_name:$object_type"];
		
		if ( $object_type && $access_name && isset( $optval["$src_name:$object_type:$access_name"] ) )
			$retval = $optval["$src_name:$object_type:$access_name"];
		

		// if no match was found for a source request, accept any non-empty otype match
		if ( ! $object_type && ! isset($retval) )
			foreach ( $optval as $src_otype => $val )
				if ( $val && ( 0 === strpos( $src_otype, "$src_name:" ) ) )
					$retval = $val;

		if ( ! isset($retval) )
			$retval = array();
			
		$otype_options[$key] = $retval;
		
		return $retval;
	}
	
	function awp_blend_option_array( $option_prefix = '', $option_name, $defaults, $key_dimensions = 1, $user_opt_val = -1 ) {
		if ( ! is_array($defaults) )
			$defaults = array();
		
		if ( -1 == $user_opt_val )
			$user_opt_val = get_option( $option_prefix . $option_name );
		
		if ( ! is_array($user_opt_val) )
			$user_opt_val = array();
		
		if ( isset( $defaults[$option_name] ) )
			$user_opt_val = $this->agp_merge_md_array($defaults[$option_name], $user_opt_val, $key_dimensions );
		
		return $user_opt_val;
	}
	
	// recursive function to merge two arrays with a specified number of key dimension
	// supports absent keys in either array, with arr_custom values taking precedence
	function agp_merge_md_array($arr_default, $arr_custom, $key_dimensions = 1, $current_dimension = 1 ) {
		if ( $current_dimension == $key_dimensions )
			return array_merge($arr_default, $arr_custom);
		else {
			$opt_keys = array_merge( array_keys($arr_default), array_keys($arr_custom) );
			foreach ($opt_keys as $key_name) {
				if ( ! isset($arr_custom[$key_name]) ) $arr_custom[$key_name] = array();
				if ( ! isset($arr_default[$key_name]) ) $arr_default[$key_name] = array();
				$arr_custom[$key_name] = $this->agp_merge_md_array($arr_default[$key_name], $arr_custom[$key_name], $key_dimensions, $current_dimension + 1);
			}
			
			return $arr_custom;
		}
	}
	
	function get_exception_fields( $rs_obj, $extra_data = array() ) {			
		if ( ! $rolename_arr = explode( '_', $rs_obj->role_name ) )
			return false;
		
		$data = array();
		
		$rs_base_role = $rolename_arr[ count($rolename_arr) - 1 ];
		
		switch( $rs_base_role ) {
			case 'contributor':
			case 'author':
			case 'editor':
			case 'associate':
			case 'revisor':
			case 'manager' :
			case 'assigner':
				$data['operation'] = 'edit';
				// no break
				
			case 'reader':
				$data['operation'] = 'read';
			
				if ( 'private' == $rolename_arr[0] ) {
					$data['for_item_type'] = implode( '_', array_slice( $rolename_arr, 1, count($rolename_arr) - 2 ) );  // $arr[1];  - unknown number of elems because type name may have underscores
				} else {
					$data['for_item_type'] = implode( '_', array_slice( $rolename_arr, 0, count($rolename_arr) - 1 ) );  // $arr[0];
				}
				
				$scope = ( isset($rs_obj->topic) ) ? $rs_obj->topic : $rs_obj->scope;
				if ( 'term' == $scope ) {
					$data['via_item_type'] = $rs_obj->src_or_tx_name;
				} else {
					$data['via_item_type'] = '';
				}
			
				/*
				if ( 'group' == $rs_obj->src_or_tx_name ) {
					$data['operation'] = 'manage';
					$data['for_item_type'] = 'pp_group';
				}
				*/
			
				return $data;
				
			default:
				return false;
		} // end switch
	}
	
	function load_role_defs() {
		$this->rs_role_caps = apply_filters('define_role_caps_rs', $this->cr_role_caps() );
		
		if ( $user_role_caps = (array) get_option( 'scoper_user_role_caps' ) )
			$this->add_role_caps( $user_role_caps );

		//$this->log_cap_usage( $this->role_defs, $this->cap_defs );  // add any otype associations from new user_role_caps, but don't remove an otype association due to disabled_role_caps

		if ( $disabled_role_caps = (array) get_option( 'scoper_disabled_role_caps' ) )
			$this->remove_role_caps( $disabled_role_caps );
			
		foreach( array( 'subscriber', 'contributor', 'author', 'editor' ) as $role_name ) {
			if ( $role = get_role( $role_name ) )
				$this->wp_role_caps[$role_name] = $role->capabilities;
			else
				$this->wp_role_caps[$role_name] = array();
		}
		
		$this->post_type_obj = get_post_type_object( 'post' );
		$this->post_caps = array( 'read_private_posts', 'edit_posts', 'edit_published_posts', 'edit_others_posts', 'delete_posts', 'delete_published_posts', 'delete_others_posts', 'publish_posts' );
		$this->post_general_caps = array( 'upload_files' );
		
		$this->all_pattern_role_caps = array_fill_keys( $this->post_general_caps, true );
		foreach( $this->post_caps as $prop )
			$this->all_pattern_role_caps[ $this->post_type_obj->cap->$prop ] = true;
		
		if ( $this->awp_ver('3.5') ) {
			$this->post_caps[]= 'create_posts';
			$this->all_pattern_role_caps[ $this->post_type_obj->cap->$prop ] = true;
		}
	}
	
	function add_role_caps( $user_role_caps ) {
		foreach( array_keys( $user_role_caps ) as $role_handle ) {
			if ( $user_role_caps[$role_handle] ) {
				if( isset( $this->rs_role_caps[$role_handle] ) )
					$this->rs_role_caps[$role_handle] = array_merge($this->rs_role_caps[$role_handle], $user_role_caps[$role_handle]);
				else
					$this->rs_role_caps[$role_handle] = $user_role_caps[$role_handle];
			}
		}
	}
	
	function remove_role_caps( $disabled_role_caps ) {
		foreach ( array_keys($this->rs_role_caps) as $role_handle )
			if ( ! empty($disabled_role_caps[$role_handle]) )
				$this->rs_role_caps[$role_handle] = array_diff_key($this->rs_role_caps[$role_handle], $disabled_role_caps[$role_handle]);
	}
	
	//note: rs_ is a role type prefix which is required for array key, but will be stripped off for name property
	function cr_role_caps() {
		$arr = array(
			'rs_link_reader' => array(
				'read' => true
			),
			'rs_link_editor' => array(
				'read' => true,
				'manage_links' => true
			),
			'rs_link_category_manager' => array(
				'manage_categories' => true
			),
			'rs_group_manager' => array(
				'manage_groups' => true,
				'recommend_group_membership' => true,
				'request_group_membership' => true
			)
		); // end role_caps array
		
		//if ( defined( 'USER_QUERY_RS' ) ) {
			$arr['rs_group_moderator'] = array(
				'recommend_group_membership' => true,
				'request_group_membership' => true
			);
			
			$arr['rs_group_applicant'] = array(
				'request_group_membership' => true
			);
		//}
		
		$arr = array_merge( $arr, $this->cr_post_role_caps() );
		$arr = array_merge( $arr, $this->cr_taxonomy_role_caps() );
		
		return $arr;
	}

	function cr_post_role_caps() {
		$arr = array();
		
		$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );

		$use_post_types = (array) get_option( 'scoper_use_post_types' );

		$force_create_posts_cap = $this->awp_ver( '3.5-beta' ) && get_option( 'scoper_define_create_posts_cap' );
		
		foreach ( $post_types as $name => $post_type_obj ) {
			if ( isset( $use_post_types[$name] ) && ! $use_post_types[$name] )
				continue;

			$cap = $post_type_obj->cap;
			
			$arr["rs_{$name}_reader"] = array(
				"read" => true
			);
			$arr["rs_private_{$name}_reader"] = array(
				$cap->read_private_posts => true,
				"read" => true
			);
			
			$arr["rs_{$name}_contributor"] = array(
				$cap->edit_posts => true,
				$cap->delete_posts => true,
				"read" => true
			);
			if ( $force_create_posts_cap )
				$arr["rs_{$name}_contributor"][$cap->create_posts] = true;
			
			if ( defined( 'RVY_VERSION' ) ) {
				$arr["rs_{$name}_revisor"] = array(
					$cap->edit_posts => true,
					$cap->delete_posts => true,
					"read" => true,
					$cap->read_private_posts => true,
					$cap->edit_others_posts => true
				);
			}
			if ( $force_create_posts_cap )
				$arr["rs_{$name}_revisor"][$cap->create_posts] = true;
		
			$arr["rs_{$name}_author"] = array(
				"upload_files" => true,
				$cap->publish_posts => true,
				$cap->edit_published_posts => true,
				$cap->delete_published_posts => true,
				$cap->edit_posts => true,
				$cap->delete_posts => true,
				"read" => true
			);
			if ( $force_create_posts_cap )
				$arr["rs_{$name}_author"][$cap->create_posts] = true;
			
			$arr["rs_{$name}_editor"] = array(
				"moderate_comments" => true,
				$cap->delete_others_posts => true,
				$cap->edit_others_posts => true,
				"upload_files" => true,
				"unfiltered_html" => true,
				$cap->publish_posts => true,
				$cap->delete_private_posts => true,
				$cap->edit_private_posts => true,
				$cap->delete_published_posts => true,
				$cap->edit_published_posts => true,
				$cap->delete_posts => true,
				$cap->edit_posts => true,
				$cap->read_private_posts => true,
				"read" => true
			);
			if ( $force_create_posts_cap )
				$arr["rs_{$name}_editor"][$cap->create_posts] = true;
			
			// Note: create_child_pages should only be present in associate role, which is used as an object-assigned alternate to blog-wide edit role
			// This way, blog-assignment of author role allows user to create new pages, but only as subpages of pages they can edit (or for which Associate role is object-assigned)
			if ( $post_type_obj->hierarchical ) {
				$plural_name = $this->plural_name_from_cap_rs( $post_type_obj );
			
				$arr["rs_{$name}_associate"] = array( 
					"create_child_{$plural_name}" => true,
					'read' => true
				);
			}
		}

		return $arr;
	}

	function cr_taxonomy_role_caps() {
		$arr = array();
		
		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
		$taxonomies ['nav_menu']= get_taxonomy( 'nav_menu' );
		
		$use_taxonomies = (array) get_option( 'scoper_use_taxonomies' );
		
		foreach ( $taxonomies as $name => $taxonomy_obj ) {	
			if ( empty( $use_taxonomies[$name] ) )
				continue;
				
			$arr["rs_{$name}_manager"][$taxonomy_obj->cap->manage_terms] = true;
			
			// in case these have been customized to a different cap name...
			$arr["rs_{$name}_manager"][$taxonomy_obj->cap->edit_terms] = true;
			$arr["rs_{$name}_manager"][$taxonomy_obj->cap->delete_terms] = true;
			//$arr["rs_{$name}_manager"]["assign_$name"] = true;  // this prevents crediting of Category Manager role from WP Editor caps
			
			$arr["rs_{$name}_assigner"]["assign_$name"] = true;
		}
		
		return $arr;	
	}
	
	function plural_name_from_cap_rs( $type_obj ) {
		if ( isset( $type_obj->cap->edit_posts ) ) {
			$test_cap = $type_obj->cap->edit_posts;
			$default_cap_prefix = 'edit_';
		} elseif( isset( $type_obj->cap->manage_terms ) ) {
			$test_cap = $type_obj->cap->manage_terms;
			$default_cap_prefix = 'manage_';
		} else
			return isset( $type_obj->name ) ? $type_obj->name . 's' : '';

		if ( ( 0 === strpos( $test_cap, $default_cap_prefix ) ) 
		&& ( false === strpos( $test_cap, '_', strlen($default_cap_prefix) ) ) )
			return substr( $test_cap, strlen($default_cap_prefix) );
		else
			return $type_obj->name . 's';
	} 
	
	function awp_ver($wp_ver_requirement) {
		static $cache_wp_ver;
		
		if ( empty($cache_wp_ver) ) {
			global $wp_version;
			$cache_wp_ver = $wp_version;
		}
		
		if ( ! version_compare($cache_wp_ver, '0', '>') ) {
			// If global $wp_version has been wiped by WP Security Scan plugin, temporarily restore it by re-including version.php
			if ( file_exists (ABSPATH . WPINC . '/version.php') ) {
				include ( ABSPATH . WPINC . '/version.php' );
				$return = version_compare($wp_version, $wp_ver_requirement, '>=');
				$wp_version = $cache_wp_ver;	// restore previous wp_version setting, assuming it was cleared for security purposes
				return $return;
			} else
				// Must be running a future version of WP which doesn't use version.php
				return true;
		}

		// normal case - global $wp_version has not been tampered with
		return version_compare($cache_wp_ver, $wp_ver_requirement, '>=');
	}
}

function rsu_plugin_info_url( $plugin_slug ) {
	return self_admin_url( "plugin-install.php?tab=plugin-information&plugin=$plugin_slug&TB_iframe=true&width=640&height=678" );
}
?>
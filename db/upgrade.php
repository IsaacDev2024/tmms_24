<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_tmms_24_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025092321) {
        // Force capability refresh - use proper cache clearing for upgrades
        purge_all_caches();
        
        // Update capabilities for all contexts where the block is installed
        $contexts = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.contextlevel, c.instanceid 
            FROM {context} c 
            INNER JOIN {block_instances} bi ON bi.id = c.instanceid 
            WHERE c.contextlevel = ? AND bi.blockname = ?", 
            [CONTEXT_BLOCK, 'tmms_24']
        );
        
        // Also update course contexts that might have the block
        $course_contexts = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.contextlevel, c.instanceid 
            FROM {context} c 
            INNER JOIN {block_instances} bi ON bi.parentcontextid = c.id 
            WHERE c.contextlevel = ? AND bi.blockname = ?", 
            [CONTEXT_COURSE, 'tmms_24']
        );
        
        foreach (array_merge($contexts, $course_contexts) as $context) {
            $context_obj = context::instance_by_id($context->id);
            if ($context_obj) {
                // Clear capability cache for this context
                accesslib_clear_role_cache($context->id);
            }
        }
        
        // Upgrade savepoint reached.
        upgrade_block_savepoint(true, 2025092321, 'tmms_24');
    }

    if ($oldversion < 2025120901) {
        // Define index to be modified in tmms_24
        $table = new xmldb_table('tmms_24');
        
        // Drop old unique index on user+course if exists
        $old_index = new xmldb_index('block_tmms_24_user_course_idx', XMLDB_INDEX_UNIQUE, ['user', 'course']);
        if ($dbman->index_exists($table, $old_index)) {
            $dbman->drop_index($table, $old_index);
        }
        
        // Add unique index on user only to prevent duplicate tests per user (regardless of course)
        $index = new xmldb_index('user_unique', XMLDB_INDEX_UNIQUE, ['user']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // TMMS-24 savepoint reached.
        upgrade_block_savepoint(true, 2025120901, 'tmms_24');
    }

    if ($oldversion < 2025121201) {
        // Modify existing tmms_24 table to support auto-save
        $table = new xmldb_table('tmms_24');
        
        // Add is_completed field if it doesn't exist
        $field = new xmldb_field('is_completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Set all existing records as completed
            $DB->execute("UPDATE {tmms_24} SET is_completed = 1");
        }
        
        // Modify item fields to allow NULL for partial saves
        for ($i = 1; $i <= 24; $i++) {
            $field = new xmldb_field('item' . $i, XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }
        }
        
        // Modify age and gender to allow NULL initially
        $field = new xmldb_field('age', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        
        $field = new xmldb_field('gender', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        
        // Modify score fields to allow NULL initially
        $field = new xmldb_field('percepcion_score', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        
        $field = new xmldb_field('comprension_score', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        
        $field = new xmldb_field('regulacion_score', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        // TMMS-24 savepoint reached.
        upgrade_block_savepoint(true, 2025121201, 'tmms_24');
    }

    return true;
}

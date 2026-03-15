<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. kakehashi_guardian - Add 11 missing domain/goal columns
        // =====================================================================
        Schema::table('kakehashi_guardian', function (Blueprint $table) {
            $table->text('student_wish')->nullable();
            $table->text('home_challenges')->nullable();
            $table->text('short_term_goal')->nullable();
            $table->text('long_term_goal')->nullable();
            $table->text('domain_health_life')->nullable();
            $table->text('domain_motor_sensory')->nullable();
            $table->text('domain_cognitive_behavior')->nullable();
            $table->text('domain_language_communication')->nullable();
            $table->text('domain_social_relations')->nullable();
            $table->text('other_challenges')->nullable();
            $table->boolean('is_hidden')->default(false);
        });

        // =====================================================================
        // 2. students - Add desired days and support_plan_start_type
        // =====================================================================
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('desired_monday')->default(false);
            $table->boolean('desired_tuesday')->default(false);
            $table->boolean('desired_wednesday')->default(false);
            $table->boolean('desired_thursday')->default(false);
            $table->boolean('desired_friday')->default(false);
            $table->boolean('desired_saturday')->default(false);
            $table->boolean('desired_sunday')->default(false);
            $table->string('support_plan_start_type')->nullable()->comment('current or next');
        });

        // =====================================================================
        // 3. individual_support_plans - Add manager, goal dates, flags, etc.
        // =====================================================================
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->string('manager_name')->nullable();
            $table->date('long_term_goal_date')->nullable();
            $table->date('short_term_goal_date')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('guardian_confirmed')->default(false);
            $table->timestamp('guardian_confirmed_at')->nullable();
            $table->foreignId('source_monitoring_id')->nullable();
            $table->timestamp('basis_generated_at')->nullable();
            $table->string('staff_signer_name')->nullable();
        });

        // =====================================================================
        // 4. monitoring_records - Add student_name, comments, draft, hidden, etc.
        // =====================================================================
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->string('student_name')->nullable();
            $table->text('short_term_goal_comment')->nullable();
            $table->text('long_term_goal_comment')->nullable();
            $table->boolean('is_draft')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->date('guardian_signature_date')->nullable();
            $table->date('staff_signature_date')->nullable();
            $table->string('staff_signer_name')->nullable();
        });

        // =====================================================================
        // 5. monitoring_details - Add plan_detail_id and timestamps
        // =====================================================================
        Schema::table('monitoring_details', function (Blueprint $table) {
            $table->foreignId('plan_detail_id')->nullable()->constrained('support_plan_details')->nullOnDelete();
            $table->timestamps();
        });

        // =====================================================================
        // 6. support_plan_details - Add sub_category, achievement_date, etc.
        // =====================================================================
        Schema::table('support_plan_details', function (Blueprint $table) {
            $table->string('sub_category')->nullable();
            $table->date('achievement_date')->nullable();
            $table->text('staff_organization')->nullable();
            $table->text('notes')->nullable();
            $table->integer('priority')->default(3);
        });

        // =====================================================================
        // 7. kakehashi_staff - Add other_challenges and is_hidden
        // =====================================================================
        Schema::table('kakehashi_staff', function (Blueprint $table) {
            $table->text('other_challenges')->nullable();
            $table->boolean('is_hidden')->default(false);
        });

        // =====================================================================
        // 8. newsletters - Add v51 columns
        // =====================================================================
        Schema::table('newsletters', function (Blueprint $table) {
            $table->text('weekly_intro')->nullable();
            $table->text('elementary_report')->nullable();
            $table->text('junior_report')->nullable();
        });

        // =====================================================================
        // 9. meeting_requests - Make staff_id nullable and add new columns
        // =====================================================================
        // Drop the existing foreign key constraint first, then re-add as nullable
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });

        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->change();
            $table->string('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('guardian_counter_message')->nullable();
            $table->text('staff_counter_message')->nullable();
        });

        // Re-add the foreign key constraint (nullable)
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // =====================================================================
        // 10. event_registrations - Add message_id and notes
        // =====================================================================
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable();
            $table->text('notes')->nullable();
        });

        // =====================================================================
        // 11. daily_records - Add support_plan_id
        // =====================================================================
        Schema::table('daily_records', function (Blueprint $table) {
            $table->foreignId('support_plan_id')->nullable();
        });

        // =====================================================================
        // 12. chat_messages - Fix sender_type CHECK to include 'admin'
        // =====================================================================
        DB::statement("ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS chat_messages_sender_type_check");
        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_sender_type_check CHECK (sender_type IN ('staff', 'guardian', 'admin'))");

        // =====================================================================
        // 13. Add missing unique constraints
        // =====================================================================
        Schema::table('absence_notifications', function (Blueprint $table) {
            $table->unique(['student_id', 'absence_date']);
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->unique(['student_id', 'guardian_id']);
        });

        Schema::table('integrated_notes', function (Blueprint $table) {
            $table->unique(['daily_record_id', 'student_id']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->unique(['holiday_date', 'classroom_id']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unique(['event_id', 'student_id']);
        });

        Schema::table('student_chat_rooms', function (Blueprint $table) {
            $table->unique(['student_id']);
        });

        Schema::table('kakehashi_staff', function (Blueprint $table) {
            $table->unique(['period_id', 'student_id']);
        });

        Schema::table('kakehashi_guardian', function (Blueprint $table) {
            $table->unique(['period_id', 'student_id']);
        });

        Schema::table('daily_records', function (Blueprint $table) {
            $table->unique(['record_date', 'staff_id', 'activity_name']);
        });

        Schema::table('student_records', function (Blueprint $table) {
            $table->unique(['daily_record_id', 'student_id']);
        });

        // =====================================================================
        // 14. Create send_history table
        // =====================================================================
        Schema::create('send_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integrated_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
        });
    }

    public function down(): void
    {
        // Drop send_history table
        Schema::dropIfExists('send_history');

        // Remove unique constraints
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropUnique(['daily_record_id', 'student_id']);
        });
        Schema::table('daily_records', function (Blueprint $table) {
            $table->dropUnique(['record_date', 'staff_id', 'activity_name']);
        });
        Schema::table('kakehashi_guardian', function (Blueprint $table) {
            $table->dropUnique(['period_id', 'student_id']);
        });
        Schema::table('kakehashi_staff', function (Blueprint $table) {
            $table->dropUnique(['period_id', 'student_id']);
        });
        Schema::table('student_chat_rooms', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'student_id']);
        });
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['holiday_date', 'classroom_id']);
        });
        Schema::table('integrated_notes', function (Blueprint $table) {
            $table->dropUnique(['daily_record_id', 'student_id']);
        });
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'guardian_id']);
        });
        Schema::table('absence_notifications', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'absence_date']);
        });

        // Revert chat_messages CHECK constraint
        DB::statement("ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS chat_messages_sender_type_check");
        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_sender_type_check CHECK (sender_type IN ('staff', 'guardian'))");

        // Remove added columns (reverse order)
        Schema::table('daily_records', function (Blueprint $table) {
            $table->dropColumn('support_plan_id');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['message_id', 'notes']);
        });

        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->dropColumn(['confirmed_by', 'confirmed_at', 'is_completed', 'completed_at', 'guardian_counter_message', 'staff_counter_message']);
        });

        // Revert staff_id to NOT NULL
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable(false)->change();
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropColumn(['weekly_intro', 'elementary_report', 'junior_report']);
        });

        Schema::table('kakehashi_staff', function (Blueprint $table) {
            $table->dropColumn(['other_challenges', 'is_hidden']);
        });

        Schema::table('support_plan_details', function (Blueprint $table) {
            $table->dropColumn(['sub_category', 'achievement_date', 'staff_organization', 'notes', 'priority']);
        });

        Schema::table('monitoring_details', function (Blueprint $table) {
            $table->dropForeign(['plan_detail_id']);
            $table->dropColumn(['plan_detail_id', 'created_at', 'updated_at']);
        });

        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->dropColumn(['student_name', 'short_term_goal_comment', 'long_term_goal_comment', 'is_draft', 'is_hidden', 'guardian_signature_date', 'staff_signature_date', 'staff_signer_name']);
        });

        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn(['manager_name', 'long_term_goal_date', 'short_term_goal_date', 'is_hidden', 'guardian_confirmed', 'guardian_confirmed_at', 'source_monitoring_id', 'basis_generated_at', 'staff_signer_name']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['desired_monday', 'desired_tuesday', 'desired_wednesday', 'desired_thursday', 'desired_friday', 'desired_saturday', 'desired_sunday', 'support_plan_start_type']);
        });

        Schema::table('kakehashi_guardian', function (Blueprint $table) {
            $table->dropColumn(['student_wish', 'home_challenges', 'short_term_goal', 'long_term_goal', 'domain_health_life', 'domain_motor_sensory', 'domain_cognitive_behavior', 'domain_language_communication', 'domain_social_relations', 'other_challenges', 'is_hidden']);
        });
    }
};

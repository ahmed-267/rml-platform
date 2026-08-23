<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_catastro_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('unknown');
            $table->string('verification_status')->default('not_checked');
            $table->string('cadastral_reference')->nullable();
            $table->string('cadastral_address')->nullable();
            $table->string('property_use')->nullable();
            $table->decimal('constructed_area_m2', 12, 2)->nullable();
            $table->decimal('parcel_area_m2', 12, 2)->nullable();
            $table->unsignedSmallInteger('construction_year')->nullable();
            $table->json('geometry')->nullable();
            $table->text('match_summary')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('lookup_at')->nullable();
            $table->foreignId('looked_up_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'verification_status']);
        });

        Schema::create('lead_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('not_started');
            $table->unsignedInteger('version')->default(1);
            $table->string('current_step')->default('property');
            $table->foreignId('surveyor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('survey_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Survey-confirmed property (separate from lead submission)
            $table->string('confirmed_address')->nullable();
            $table->string('property_type')->nullable();
            $table->string('occupancy_type')->nullable();
            $table->unsignedTinyInteger('number_of_floors')->nullable();
            $table->unsignedSmallInteger('approx_construction_year')->nullable();
            $table->boolean('occupied')->nullable();
            $table->boolean('homeowner_present')->nullable();
            $table->string('general_condition')->nullable();
            $table->boolean('location_confirmed')->nullable();
            $table->text('discrepancy_notes')->nullable();

            // Access assessment
            $table->json('access')->nullable();
            $table->json('no_access')->nullable();
            $table->json('loft_hatch')->nullable();

            // Aggregated measurements (section detail in child table)
            $table->decimal('surveyed_floor_area_m2', 12, 2)->nullable();
            $table->decimal('surveyed_installation_area_m2', 12, 2)->nullable();
            $table->string('measurement_method')->nullable();
            $table->string('measurement_confidence')->nullable();
            $table->date('measurement_date')->nullable();
            $table->text('measurement_notes')->nullable();

            // Scheme-specific payload
            $table->json('scheme_inspection')->nullable();

            // Homeowner confirmation
            $table->json('homeowner_confirmation')->nullable();

            // Risks summary
            $table->json('risks')->nullable();
            $table->text('surveyor_recommendation')->nullable();
            $table->text('seller_visible_notes')->nullable();
            $table->text('buyer_visible_notes')->nullable();
            $table->text('internal_audit_notes')->nullable();

            // Auditor-approved final measurements (never overwrite submitted/catastro/survey)
            $table->decimal('auditor_approved_area_m2', 12, 2)->nullable();
            $table->decimal('auditor_approved_installation_area_m2', 12, 2)->nullable();
            $table->text('auditor_decision_notes')->nullable();
            $table->text('correction_request')->nullable();
            $table->json('correction_sections')->nullable();

            // Future AI suggestions (separate storage)
            $table->json('ai_suggestions')->nullable();

            $table->timestamps();

            $table->unique('lead_id');
            $table->index(['status', 'surveyor_user_id']);
        });

        Schema::create('lead_survey_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_survey_id')->constrained('lead_surveys')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status');
            $table->string('event');
            $table->json('snapshot');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lead_survey_id', 'version']);
        });

        Schema::create('lead_survey_measurement_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_survey_id')->constrained('lead_surveys')->cascadeOnDelete();
            $table->string('name');
            $table->string('section_type')->nullable();
            $table->decimal('length_m', 10, 2)->nullable();
            $table->decimal('width_m', 10, 2)->nullable();
            $table->decimal('height_m', 10, 2)->nullable();
            $table->decimal('calculated_area_m2', 12, 2)->nullable();
            $table->decimal('manual_area_m2', 12, 2)->nullable();
            $table->string('measurement_method')->nullable();
            $table->string('confidence')->nullable();
            $table->boolean('is_estimate')->default(false);
            $table->boolean('area_not_accessed')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lead_survey_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_survey_id')->constrained('lead_surveys')->cascadeOnDelete();
            $table->foreignId('measurement_section_id')->nullable()
                ->constrained('lead_survey_measurement_sections')->nullOnDelete();
            $table->string('category');
            $table->string('survey_section')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('orientation')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->string('caption')->nullable();
            $table->string('review_status')->default('pending');
            $table->text('auditor_comment')->nullable();
            $table->boolean('for_ai_measurement')->default(false);
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('survey_eligibility_status')->nullable()->after('cadastral_verified_at');
            $table->decimal('submitted_property_area_m2', 12, 2)->nullable()->after('size_m2');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['survey_eligibility_status', 'submitted_property_area_m2']);
        });

        Schema::dropIfExists('lead_survey_evidence');
        Schema::dropIfExists('lead_survey_measurement_sections');
        Schema::dropIfExists('lead_survey_versions');
        Schema::dropIfExists('lead_surveys');
        Schema::dropIfExists('lead_catastro_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameLegacyTables();
        $this->normalizeNasTable();
        $this->createAuthorizationTables();
        $this->createAccountingTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('radpostauth');
        Schema::dropIfExists('radacct');
        Schema::dropIfExists('radgroupcheck');
        Schema::dropIfExists('radreply');

        if (Schema::hasTable('nas') && ! Schema::hasTable('radius_nas')) {
            Schema::rename('nas', 'radius_nas');
        }
        if (Schema::hasTable('radgroupreply') && ! Schema::hasTable('radius_group_replies')) {
            Schema::rename('radgroupreply', 'radius_group_replies');
        }
        if (Schema::hasTable('radusergroup') && ! Schema::hasTable('radius_user_groups')) {
            Schema::rename('radusergroup', 'radius_user_groups');
        }
        if (Schema::hasTable('radcheck') && ! Schema::hasTable('radius_users')) {
            Schema::rename('radcheck', 'radius_users');
        }
    }

    private function renameLegacyTables(): void
    {
        $renames = [
            'radius_users' => 'radcheck',
            'radius_user_groups' => 'radusergroup',
            'radius_group_replies' => 'radgroupreply',
            'radius_nas' => 'nas',
        ];

        foreach ($renames as $legacy => $stock) {
            if (Schema::hasTable($legacy) && ! Schema::hasTable($stock)) {
                Schema::rename($legacy, $stock);
            }
        }
    }

    private function normalizeNasTable(): void
    {
        if (! Schema::hasTable('nas')) {
            return;
        }

        DB::table('nas')->whereNull('secret')->delete();

        Schema::table('nas', function (Blueprint $table): void {
            $table->string('type', 32)->default('other');
            $table->unsignedInteger('ports')->nullable();
            $table->text('server')->nullable();
            $table->text('community')->nullable();
            $table->text('description')->nullable();
            $table->text('secret')->nullable(false)->change();
        });
    }

    private function createAuthorizationTables(): void
    {
        if (! Schema::hasTable('radreply')) {
            Schema::create('radreply', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->string('op', 2)->default('=');
                $table->text('value')->default('');
                $table->timestamps();
                $table->index(['username', 'attribute']);
                $table->index(['tenant_id', 'username']);
            });
        }

        if (! Schema::hasTable('radgroupcheck')) {
            Schema::create('radgroupcheck', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->string('op', 2)->default('==');
                $table->text('value')->default('');
                $table->timestamps();
                $table->index(['groupname', 'attribute']);
                $table->index(['tenant_id', 'groupname']);
            });
        }
    }

    private function createAccountingTables(): void
    {
        if (! Schema::hasTable('radacct')) {
            Schema::create('radacct', function (Blueprint $table): void {
                $table->bigIncrements('radacctid');
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
                $table->text('acctsessionid');
                $table->text('acctuniqueid')->unique();
                $table->text('username')->nullable();
                $table->text('realm')->nullable();
                $table->ipAddress('nasipaddress');
                $table->text('nasportid')->nullable();
                $table->text('nasporttype')->nullable();
                $table->timestampTz('acctstarttime')->nullable();
                $table->timestampTz('acctupdatetime')->nullable();
                $table->timestampTz('acctstoptime')->nullable();
                $table->bigInteger('acctinterval')->nullable();
                $table->bigInteger('acctsessiontime')->nullable();
                $table->text('acctauthentic')->nullable();
                $table->text('connectinfo_start')->nullable();
                $table->text('connectinfo_stop')->nullable();
                $table->bigInteger('acctinputoctets')->nullable();
                $table->bigInteger('acctoutputoctets')->nullable();
                $table->text('calledstationid')->nullable();
                $table->text('callingstationid')->nullable();
                $table->text('acctterminatecause')->nullable();
                $table->text('servicetype')->nullable();
                $table->text('framedprotocol')->nullable();
                $table->ipAddress('framedipaddress')->nullable();
                $table->ipAddress('framedipv6address')->nullable();
                $table->ipAddress('framedipv6prefix')->nullable();
                $table->text('framedinterfaceid')->nullable();
                $table->ipAddress('delegatedipv6prefix')->nullable();
                $table->text('class')->nullable();
                $table->index(['nasipaddress', 'acctstarttime']);
                $table->index(['acctstarttime', 'username']);
                $table->index(['acctstoptime', 'username']);
                $table->index(['tenant_id', 'acctstarttime']);
            });
        }

        if (! Schema::hasTable('radpostauth')) {
            Schema::create('radpostauth', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->text('username');
                $table->text('pass')->nullable();
                $table->text('reply')->nullable();
                $table->text('calledstationid')->nullable();
                $table->text('callingstationid')->nullable();
                $table->timestampTz('authdate')->useCurrent();
                $table->text('class')->nullable();
                $table->index('username');
                $table->index('class');
            });
        }
    }
};

<?php

declare(strict_types=1);

namespace Jengo\Pesa\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePesaTransactionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'gateway' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'gateway_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'receipt_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'stk_push',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'pending',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 0.00,
            ],
            'currency' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'default'    => 'KES',
            ],
            'payer_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'payer_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'payer_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'failure_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'raw_request' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'raw_response' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['gateway', 'gateway_reference']);
        $this->forge->addKey('reference');
        $this->forge->addKey('receipt_number');
        $this->forge->addKey('status');
        $this->forge->createTable('pesa_transactions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('pesa_transactions', true);
    }
}

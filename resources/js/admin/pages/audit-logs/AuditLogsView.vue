<script setup>
import { reactive } from 'vue';

const log = reactive({
    user: '',
    actor_email: '',
    actor_role: '',
    actor_type: '',
    action_text: '',
    action_color: '',
    module: '',
    description: '',
    old_values: null,
    new_values: null,
    metadata: null,
    ip_address: '',
    user_agent: '',
    created_at: ''
});

const getAuditLog = (audit_log) => {
    log.user = audit_log.user;
    log.actor_email = audit_log.actor_email;
    log.actor_role = audit_log.actor_role;
    log.actor_type = audit_log.actor_type;
    log.action_text = audit_log.action_text;
    log.action_color = audit_log.action_color;
    log.module = audit_log.module;
    log.description = audit_log.description;
    log.old_values = audit_log.old_values;
    log.new_values = audit_log.new_values;
    log.metadata = audit_log.metadata;
    log.ip_address = audit_log.ip_address;
    log.user_agent = audit_log.user_agent;
    log.created_at = audit_log.created_at;
};

defineExpose({ getAuditLog });

const roleBadgeClass = (type) => type === 'admin'
    ? 'bg-primary-subtle text-primary'
    : 'bg-success-subtle text-success';

const hasValues = () => Boolean(log.old_values) || Boolean(log.new_values);

const hasMetadata = () => log.metadata && Object.keys(log.metadata).length > 0;

const formatJson = (data) => {
    if (!data) return 'N/A';
    try {
        return JSON.stringify(typeof data === 'string' ? JSON.parse(data) : data, null, 2);
    } catch (e) {
        return data;
    }
};


</script>
<template>
    <div class="modal fade" id="view-audit-log" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">Audit Log Details</h6>
                    <button class="btn-close" data-bs-dismiss="modal" title="Close"></button>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">User:</label>
                                <p class="mb-0">
                                    {{ log.user }}
                                    <small class="text-muted d-block">{{ log.actor_email || '—' }}</small>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Date & Time:</label>
                                <p class="mb-0">{{ log.created_at }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Role:</label>
                                <p class="mb-0">
                                    <span class="badge" :class="roleBadgeClass(log.actor_type)">
                                        {{ log.actor_role || '—' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Action:</label>
                                <p class="mb-0">
                                    <span :class="log.action_color">{{ log.action_text }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Module:</label><br>
                                <span class="badge bg-info-subtle text-info">{{ log.module }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">IP Address:</label>
                                <p class="mb-0">{{ log.ip_address || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description:</label>
                        <p class="mb-0">{{ log.description }}</p>
                    </div>

                    <div class="mb-3" v-if="log.user_agent">
                        <label class="form-label fw-bold">User Agent:</label>
                        <p class="mb-0 text-break">{{ log.user_agent }}</p>
                    </div>

                    <div class="row" v-if="hasValues()">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Old Values:</label>
                                <div class="border rounded p-2 bg-body" style="max-height: 300px; overflow-y: auto;">
                                    <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ formatJson(log.old_values) }}</pre>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">New Values:</label>
                                <div class="border rounded p-2 bg-body" style="max-height: 300px; overflow-y: auto;">
                                    <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ formatJson(log.new_values) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="hasMetadata()">
                        <label class="form-label fw-bold">Metadata:</label>
                        <div class="border rounded p-2 bg-body" style="max-height: 300px; overflow-y: auto;">
                            <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ formatJson(log.metadata) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

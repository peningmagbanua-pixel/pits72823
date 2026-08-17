<script setup>
import { reactive } from 'vue';

const log = reactive({
    user: '',
    action_text: '',
    action_color: '',
    module: '',
    description: '',
    old_values: null,
    new_values: null,
    created_at: ''
});

const getAuditLog = (audit_log) => {
    log.user = audit_log.user;
    log.action_text = audit_log.action_text;
    log.action_color = audit_log.action_color;
    log.module = audit_log.module;
    log.description = audit_log.description;
    log.old_values = audit_log.old_values;
    log.new_values = audit_log.new_values;
    log.created_at = audit_log.created_at;
};

defineExpose({ getAuditLog });

const formatJson = (data) => {
    if (!data) return 'N/A';
    try {
        return JSON.stringify(JSON.parse(data), null, 2);
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
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">User:</label>
                                <p class="mb-0">{{ log.user }}</p>
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
                                <label class="form-label fw-bold">Action:</label>
                                <p class="mb-0">
                                    <span :class="log.action_color">{{ log.action_text }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Module:</label><br>
                                <span class="badge bg-info-subtle text-info">{{ log.module }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description:</label>
                        <p class="mb-0">{{ log.description }}</p>
                    </div>

                    <div class="row">
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
                </div>
            </div>
        </div>
    </div>
</template>

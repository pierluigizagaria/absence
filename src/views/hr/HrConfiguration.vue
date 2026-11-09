<template>
	<div class="configuration">
		<h2>{{ t('absence', 'Configuration') }}</h2>
		<p v-if="!store.session.isHr" role="alert">{{ t('absence', 'HR role required') }}</p>
		<template v-else>
			<p>{{ t('absence', 'Manage absence types. Disable a type to stop new requests while keeping its history.') }}</p>
			<p v-if="store.session.privateAbsences">{{ t('absence', 'Employee privacy is enabled: colleagues cannot see each other’s absences.') }}</p>
			<a v-if="store.session.isAdmin" :href="adminUrl">{{ t('absence', 'General settings: privacy, replacement field, approvals and annual entitlement') }}</a>
			<p v-else>{{ t('absence', 'General settings are managed by a Nextcloud administrator.') }}</p>
			<p v-if="error" role="alert" class="configuration__error">{{ error }}</p>
			<p v-if="notice" role="status">{{ notice }}</p>
			<p v-if="loading">{{ t('absence', 'Loading…') }}</p>
			<label><input v-model="showInactive" type="checkbox"> {{ t('absence', 'Show disabled types') }}</label>
			<section v-for="type in visibleTypes" :key="type.id" class="configuration__type">
				<h3>{{ type.label }}</h3>
				<NcTextField v-model="type.label" :label="t('absence', 'Type name')" :maxlength="128" :disabled="saving === type.id" />
				<div class="configuration__options">
					<label><input v-model="type.enabled" type="checkbox"> {{ t('absence', 'Available for new requests') }}</label>
					<label><input v-model="type.requiresApproval" type="checkbox"> {{ t('absence', 'Requires approval') }}</label>
					<label><input v-model="type.employeeRequestable" type="checkbox"> {{ t('absence', 'Employees can request this type') }}</label>
					<label><input v-model="type.countsAgainstBalance" type="checkbox"> {{ t('absence', 'Track a balance in days') }}</label>
					<label><input v-model="type.requiresNote" type="checkbox"> {{ t('absence', 'Reason is required') }}</label>
				</div>
				<NcButton :disabled="saving !== null || !type.label.trim()" variant="secondary" @click="save(type)">{{ t('absence', 'Save changes') }}</NcButton>
			</section>
			<form class="configuration__create" @submit.prevent="create">
				<h3>{{ t('absence', 'Add an absence type') }}</h3>
				<NcTextField v-model="newName" :label="t('absence', 'New type name')" :maxlength="128" required />
				<NcButton type="submit" variant="primary" :disabled="saving !== null || !newName.trim()">{{ t('absence', 'Add type') }}</NcButton>
			</form>
		</template>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import api from '../../api.js'
import { store } from '../../store.js'

export default {
	name: 'HrConfiguration',
	components: { NcButton, NcTextField },
	data() {
		return { store, showInactive: false, types: [], newName: '', loading: true, saving: null, error: '', notice: '', adminUrl: generateUrl('/settings/admin/absence') }
	},
	async mounted() {
		if (!store.session.isHr) {
			this.loading = false
			return
		}
		try {
			this.types = (await api.listLeaveTypes(false)).map((type) => ({ ...type, _storedEnabled: type.enabled, label: t('absence', type.label) }))
		} catch (error) {
			this.fail(error)
		} finally {
			this.loading = false
		}
	},
	computed: {
		visibleTypes() { return this.types.filter((type) => this.showInactive || type._storedEnabled) },
	},
	methods: {
		t,
		fail(error) {
			this.error = error.response?.data?.message || t('absence', 'Could not save configuration. Please try again.')
		},
		async save(type) {
			this.saving = type.id
			this.error = ''
			this.notice = ''
			try {
				await api.updateLeaveType(type.id, { label: type.label.trim(), enabled: type.enabled, requiresApproval: type.requiresApproval, employeeRequestable: type.employeeRequestable, countsAgainstBalance: type.countsAgainstBalance, requiresNote: type.requiresNote })
				type._storedEnabled = type.enabled
				await store.loadLeaveTypes()
				this.notice = t('absence', 'Configuration saved')
			} catch (error) {
				this.fail(error)
			} finally {
				this.saving = null
			}
		},
		async create() {
			this.saving = 'new'
			this.error = ''
			this.notice = ''
			try {
				const type = await api.createLeaveType({ key: 'type_' + crypto.randomUUID().replaceAll('-', '').slice(0, 20), label: this.newName.trim(), icon: '', requiresReplacement: false, requiresApproval: true, countsAgainstBalance: false, employeeRequestable: true, sortOrder: this.types.length })
				this.types.push({ ...type, _storedEnabled: true })
				this.newName = ''
				await store.loadLeaveTypes()
				this.notice = t('absence', 'Configuration saved')
			} catch (error) {
				this.fail(error)
			} finally {
				this.saving = null
			}
		},
	},
}
</script>

<style scoped lang="scss">
.configuration {
	max-width: 840px;
	margin: 0 auto;
	padding: calc(var(--default-grid-baseline, 4px) * 5);
	line-height: 1.5;
	&__type, &__create {
		padding-block: 24px;
		border-block-start: 1px solid var(--color-border);
		margin-block-start: 24px;
	}
	&__options {
		display: flex;
		flex-direction: column;
		margin-block: 12px;
		label { display: flex; align-items: center; gap: 8px; min-height: 44px; }
	}
	&__error { color: var(--color-error-text); }
	h2, h3 { margin-block: 0 12px; }
	p { margin-block: 12px; }
}
</style>

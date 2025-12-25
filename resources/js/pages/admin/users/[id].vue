<script setup>
import api from '@/utils/api'
import UserBioPanel from '@/views/user/UserBioPanel.vue'
import UserTabAccount from '@/views/user/UserTabAccount.vue'
import UserTabBillingsPlans from '@/views/user/UserTabBillingsPlans.vue'
import UserTabConnections from '@/views/user/UserTabConnections.vue'
import UserTabNotifications from '@/views/user/UserTabNotifications.vue'
import UserTabSecurity from '@/views/user/UserTabSecurity.vue'
import { ref } from 'vue'
import { useRoute } from 'vue-router'

definePage({
  meta: {
    action: 'view',
    subject: 'users',
  },
})

const route = useRoute()
const userTab = ref(null)

const tabs = [
  {
    icon: 'tabler-users',
    title: 'Account',
  },
  {
    icon: 'tabler-lock',
    title: 'Security',
  },
  {
    icon: 'tabler-bookmark',
    title: 'Billing & Plan',
  },
  {
    icon: 'tabler-bell',
    title: 'Notifications',
  },
  {
    icon: 'tabler-link',
    title: 'Connections',
  },
]

const { data: userData } = await useApi(`/apps/users/${ route.params.id }`)
</script>

<template>
  <VRow v-if="userData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <UserBioPanel :user-data="userData" />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VTabs
        v-model="userTab"
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ tab.title }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="userTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <UserTabAccount />
        </VWindowItem>

        <VWindowItem>
          <UserTabSecurity />
        </VWindowItem>

        <VWindowItem>
          <UserTabBillingsPlans />
        </VWindowItem>

        <VWindowItem>
          <UserTabNotifications />
        </VWindowItem>

        <VWindowItem>
          <UserTabConnections />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
  <div v-else>
    <VAlert
      type="error"
      variant="tonal"
    >
      User with ID {{ route.params.id }} not found!
    </VAlert>
  </div>
</template>

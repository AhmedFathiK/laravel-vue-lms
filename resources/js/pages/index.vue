<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import Banner from '@/views/front-pages/landing-page/banner.vue'
import ContactUs from '@/views/front-pages/landing-page/contact-us.vue'
import CustomersReview from '@/views/front-pages/landing-page/customers-review.vue'
import FaqSection from '@/views/front-pages/landing-page/faq-section.vue'
import Features from '@/views/front-pages/landing-page/features.vue'
import HomeCover from '@/views/front-pages/landing-page/home-cover.vue'
import OurTeam from '@/views/front-pages/landing-page/our-team.vue'
import PricingPlans from '@/views/front-pages/landing-page/pricing-plans.vue'
import ProductStats from '@/views/front-pages/landing-page/product-stats.vue'
import { useConfigStore } from '@core/stores/config'
import api from '@/utils/api'

const store = useConfigStore()

store.skin = 'default'
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const activeSectionId = ref()
const landingPageConfig = ref([])
const sectionRefs = ref({})

const navbarConfig = computed(() => {
  return landingPageConfig.value.find(section => section.component === 'Navbar')
})

const componentsMap = {
  HomeCover,
  Features,
  CustomersReview,
  OurTeam,
  PricingPlans,
  ProductStats,
  FaqSection,
  Banner,
  ContactUs,
}

const fetchConfig = async () => {
  try {
    const response = await api.get('/public/landing-page-settings')

    landingPageConfig.value = response
  } catch (error) {
    console.error('Failed to fetch landing page config', error)
  }
}

onMounted(() => {
  fetchConfig()
})

const observeSections = () => {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        activeSectionId.value = entry.target.id
      }
    })
  }, { threshold: 0.25 })

  // Observe all section elements
  Object.values(sectionRefs.value).forEach(refComponent => {
    const el = refComponent?.$el || refComponent
    if (el && el instanceof Element) {
      observer.observe(el)
    }
  })
}

watch(landingPageConfig, () => {
  nextTick(() => {
    observeSections()
  })
})
</script>

<template>
  <div class="landing-page-wrapper">
    <Navbar 
      :active-id="activeSectionId" 
      :config="navbarConfig?.props"
    />

    <template
      v-for="section in landingPageConfig"
      :key="section.id"
    >
      <div 
        v-if="section.visible"
        :style="section.wrapper_style"
      >
        <component
          :is="componentsMap[section.component]"
          :id="section.id"
          v-bind="section.props"
          :ref="el => sectionRefs[section.id] = el"
        />
      </div>
    </template>

    <Footer />
  </div>
</template>

<style lang="scss">
@media (max-width: 960px) and (min-width: 600px) {
  .landing-page-wrapper {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}
</style>

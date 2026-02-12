<script setup>
import { formatCurrency } from '@/@core/utils/formatters'
import paperPlane from '@images/front-pages/icons/paper-airplane.png'
import plane from '@images/front-pages/icons/plane.png'
import pricingPlanArrow from '@images/front-pages/icons/pricing-plans-arrow.png'
import shuttleRocket from '@images/front-pages/icons/shuttle-rocket.png'

const props = defineProps({
  tag: {
    type: String,
    default: 'Pricing Plans',
  },
  title: {
    type: String,
    default: 'Tailored design plans designed for you',
  },
  subtitle: {
    type: String,
    default: 'All plans include 40+ advanced tools and features to boost your product.\nChoose the best plan to fit your needs.',
  },
  saveText: {
    type: String,
    default: 'Save 25%',
  },
  plans: {
    type: Array,
    default: () => [
      {
        title: 'Basic',
        image: paperPlane,
        monthlyPrice: 19,
        yearlyPrice: 168,
        features: [
          'Timeline',
          'Basic search',
          'Live chat widget',
          'Email marketing',
          'Custom Forms',
          'Traffic analytics',
          'Basic Support',
        ],
        supportType: 'Basic',
        supportMedium: 'Only Email',
        respondTime: 'AVG. Time: 24h',
        current: false,
      },
      {
        title: 'Favourite',
        image: plane,
        monthlyPrice: 29,
        yearlyPrice: 264,
        features: [
          'Everything in basic',
          'Timeline with database',
          'Advanced search',
          'Marketing automation',
          'Advanced chatbot',
          'Campaign management',
          'Collaboration tools',
        ],
        supportType: 'Standard',
        supportMedium: 'Email & Chat',
        respondTime: 'AVG. Time: 6h',
        current: true,
      },
      {
        title: 'Standard',
        image: shuttleRocket,
        monthlyPrice: 49,
        yearlyPrice: 444,
        features: [
          'Campaign management',
          'Timeline with database',
          'Fuzzy search',
          'A/B testing sanbox',
          'Custom permissions',
          'Social media automation',
          'Sales automation tools',
        ],
        supportType: 'Exclusive',
        supportMedium: 'Email, Chat & Google Meet',
        respondTime: 'Live Support',
        current: false,
      },
    ],
  },
})

const annualMonthlyPlanPriceToggler = ref(true)
const defaultCurrency = import.meta.env.VITE_DEFAULT_CURRENCY || 'EGP'
</script>

<template>
  <div id="pricing-plan">
    <VContainer>
      <div class="pricing-plans">
        <!-- 👉 Headers  -->
        <div class="headers d-flex justify-center flex-column align-center flex-wrap">
          <VChip
            label
            color="primary"
            class="mb-4"
            size="small"
          >
            {{ props.tag }}
          </VChip>
          <h4 class="d-flex align-center text-h4 mb-1 flex-wrap justify-center">
            <div class="position-relative me-2">
              <div class="section-title">
                {{ props.title }}
              </div>
            </div>
          </h4>
          <div class="text-center text-body-1">
            <p class="mb-0">
              {{ props.subtitle }}
            </p>
          </div>
        </div>
        <!-- 👉 Annual and monthly price toggler -->
        <div class="d-flex align-center justify-center mx-auto mt-6 mb-16">
          <VLabel
            for="pricing-plan-toggle"
            class="me-3"
          >
            Pay Monthly
          </VLabel>
          <div class="position-relative">
            <VSwitch
              id="pricing-plan-toggle"
              v-model="annualMonthlyPlanPriceToggler"
            >
              <template #label>
                <div class="text-body-1">
                  Pay Annually
                </div>
              </template>
            </VSwitch>
            <div class="position-absolute pricing-plan-arrow d-md-flex d-none">
              <VImg
                :src="pricingPlanArrow"
                class="flip-in-rtl"
                width="60"
                height="42"
              />
              <div class="text-no-wrap text-body-1 font-weight-medium">
                {{ props.saveText }}
              </div>
            </div>
          </div>
        </div>
        <VRow>
          <VCol
            v-for="(plan, index) in props.plans"
            :key="index"
            cols="12"
            md="4"
            sm="6"
          >
            <VCard :style="plan.current ? 'border:2px solid rgb(var(--v-theme-primary))' : ''">
              <VCardText class="pa-8 pt-12">
                <VImg
                  :src="plan.image"
                  width="88"
                  height="88"
                  class="mx-auto mb-8"
                />
                <h4 class="text-h4 text-center">
                  {{ plan.title }}
                </h4>
                <div class="d-flex justify-center mb-8 position-relative">
                  <div class="d-flex align-end">
                    <div class="pricing-title text-primary me-1">
                      {{ formatCurrency(annualMonthlyPlanPriceToggler ? Math.floor(plan.yearlyPrice) / 12 : plan.monthlyPrice, defaultCurrency) }}
                    </div>
                    <span class="text-disabled mb-2">/mo</span>
                  </div>

                  <!-- 👉 Annual Price -->
                  <span
                    v-show="annualMonthlyPlanPriceToggler"
                    class="annual-price-text position-absolute text-sm text-disabled"
                  >
                    {{ plan.yearlyPrice === 0 ? 'free' : `${formatCurrency(plan.yearlyPrice, defaultCurrency)}/Year` }}
                  </span>
                </div>
                <VList class="card-list">
                  <VListItem
                    v-for="(item, i) in plan.features"
                    :key="i"
                  >
                    <template #prepend>
                      <VAvatar
                        size="16"
                        :variant="!plan.current ? 'tonal' : 'elevated'"
                        color="primary"
                        class="me-3"
                      >
                        <VIcon
                          icon="tabler-check"
                          size="12"
                          :color="!plan.current ? 'primary' : 'white'"
                        />
                      </VAvatar>
                      <h6 class="text-h6">
                        {{ item }}
                      </h6>
                    </template>
                  </VListItem>
                </VList>
                <VBtn
                  block
                  :variant="plan.current ? 'elevated' : 'tonal'"
                  class="mt-8"
                  :to="{ name: 'payment' }"
                >
                  Get Started
                </VBtn>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 12px;
}

#pricing-plan {
  border-radius: 3.75rem;
  background-color: rgb(var(--v-theme-background));
}

.pricing-title {
  font-size: 38px;
  font-weight: 800;
  line-height: 52px;
}

.pricing-plans {
  margin-block: 5.25rem;
}

@media (max-width: 600px) {
  .pricing-plans {
    margin-block: 4rem;
  }
}

.save-upto-chip {
  inset-block-start: -1.5rem;
  inset-inline-end: -7rem;
}

.pricing-plan-arrow {
  inset-block-start: -0.5rem;
  inset-inline-end: -8rem;
}

.section-title {
  font-size: 24px;
  font-weight: 800;
  line-height: 36px;
}

.section-title::after {
  position: absolute;
  background: url("../../../assets/images/front-pages/icons/section-title-icon.png") no-repeat left bottom;
  background-size: contain;
  block-size: 100%;
  content: "";
  font-weight: 700;
  inline-size: 120%;
  inset-block-end: 0;
  inset-inline-start: -12%;
}

.annual-price-text {
  inset-block-end: -40%;
}
</style>

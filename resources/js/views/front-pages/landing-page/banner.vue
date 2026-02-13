<script setup>
import ctaDashborad from '@images/front-pages/landing-page/cta-dashboard.png'

const props = defineProps({
  title: {
    type: String,
    default: 'Ready to Get Started?',
  },
  subtitle: {
    type: String,
    default: 'Start your project with a 14-day free trial',
  },
  buttonText: {
    type: String,
    default: 'Get Started',
  },
  image: {
    type: String,
    default: null,
  },
})
</script>

<template>
  <div
    class="landing-cta bg-surface"
    :class="$vuetify.theme.current.dark ? 'banner-bg-dark' : 'banner-bg-light'"
  >
    <VContainer>
      <div class="d-flex justify-center justify-md-space-between flex-wrap gap-6 gap-x-10 position-relative pt-12">
        <div class="align-self-center">
          <div class="banner-title text-primary mb-1">
            {{ props.title }}
          </div>
          <h5 class="text-h5 text-medium-emphasis mb-8">
            {{ props.subtitle }}
          </h5>
          <VBtn
            color="primary"
            :to="{ name: 'payment' }"
            :size="$vuetify.display.smAndUp ? 'large' : 'default' "
          >
            {{ props.buttonText }}
          </VBtn>
        </div>

        <div class="banner-img">
          <img
            :src="props.image || ctaDashborad"
            class="w-100"
          >
        </div>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss">
.landing-cta {
  background-size: cover;
  margin-block: auto;
}

.banner-img {
  margin-block-end: -22px;
  max-inline-size: 500px;
  width: 100%;
}

.banner-title {
  font-size: 34px;
  font-weight: 700;
  line-height: 44px;
}

.banner-bg-light {
  background-image: url("@images/front-pages/backgrounds/cta-bg.png");
}

.banner-bg-dark {
  background-image: url("@images/front-pages/backgrounds/cta-bg-dark.png");
}
</style>

<script setup>
import { useMouse } from '@vueuse/core'
import { useTheme } from 'vuetify'
import { useGenerateImageVariant } from '@/@core/composable/useGenerateImageVariant'
import joinArrow from '@images/front-pages/icons/Join-community-arrow.png'
import heroDashboardImgDark from '@images/front-pages/landing-page/hero-dashboard-dark.png'
import heroDashboardImgLight from '@images/front-pages/landing-page/hero-dashboard-light.png'

const props = defineProps({
  title: {
    type: String,
    default: 'One dashboard to manage all your business',
  },
  subtitle: {
    type: String,
    default: 'Production-ready & easy to use Admin Template for Reliability and Customizability.',
  },
  buttonText: {
    type: String,
    default: 'Get early Access',
  },
  buttonLink: {
    type: String,
    default: '/#pricing-plan',
  },
  secondaryButtonText: {
    type: String,
    default: 'Join Community',
  },
  secondaryButtonLink: {
    type: String,
    default: 'https://discord.gg/12345',
  },
  secondaryButtonTarget: {
    type: Boolean,
    default: true,
  },
  imageLink: {
    type: String,
    default: '/',
  },
  imageTarget: {
    type: Boolean,
    default: true,
  },
  heroImage: {
    type: String,
    default: null,
  },
})

const theme = useTheme()
const heroDashboardImg = useGenerateImageVariant(heroDashboardImgLight, heroDashboardImgDark)
const { x, y } = useMouse({ touch: false })

const activeHeroDashboardImg = computed(() => {
  return props.heroImage ? props.heroImage : heroDashboardImg.value
})

const translateMouse = computed(() => {
  if (typeof window !== 'undefined') {
    const rotateX = ref((window.innerHeight - 1 * y.value) / 100)
    
    return { transform: `perspective(1200px) rotateX(${ rotateX.value < -40 ? -20 : rotateX.value }deg) rotateY(${ (window.innerWidth - 2 * x.value) / 100 }deg) scale3d(1,1,1)` }
  }

  // Provide a default return value when `window` is undefined
  return { transform: 'perspective(1200px) rotateX(0deg) rotateY(0deg) scale3d(1,1,1)' }
})
</script>

<template>
  <div
    id="home"
    :style="{ background: 'rgb(var(--v-theme-surface))' }"
  >
    <div id="landingHero">
      <div
        class="landing-hero"
        :class="theme.current.value.dark ? 'landing-hero-dark-bg' : 'landing-hero-light-bg'"
      >
        <VContainer>
          <div class="hero-text-box text-center px-6">
            <h1 class="hero-title mb-4">
              {{ props.title }}
            </h1>
            <h6 class="mb-6 text-h6">
              {{ props.subtitle }}
            </h6>
            <div class="position-relative">
              <h6 class="position-absolute hero-btn-item d-md-flex d-none text-h6 text-medium-emphasis">
                <a
                  :href="props.secondaryButtonLink"
                  :target="props.secondaryButtonTarget ? '_blank' : '_self'"
                  class="text-decoration-none text-medium-emphasis d-flex align-center"
                >
                  {{ props.secondaryButtonText }}
                  <VImg
                    :src="joinArrow"
                    class="flip-in-rtl ms-2"
                    width="54"
                    height="31"
                  />
                </a>
              </h6>

              <VBtn
                :size="$vuetify.display.smAndUp ? 'large' : 'default' "
                :to="props.buttonLink"
                :active="false"
              >
                {{ props.buttonText }}
              </VBtn>
            </div>
          </div>
        </VContainer>
      </div>
    </div>

    <VContainer>
      <div class="position-relative">
        <div class="hero-animation-img">
          <a
            :href="props.imageLink"
            :target="props.imageTarget ? '_blank' : '_self'"
          >
            <div
              class="hero-dashboard-img position-relative"
              :style="translateMouse"
              data-allow-mismatch
            >
              <img
                :src="activeHeroDashboardImg"
                alt="Hero Dashboard"
                class="animation-img"
              >
            </div>
          </a>
        </div>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss" scoped>
.landing-hero {
  border-radius: 0 0 50px 50px;
  padding-block: 9.75rem 22rem;
}

.hero-animation-img {
  position: relative;
  inline-size: 90%;
  margin-block-start: -25rem;
  margin-inline: auto;
}

section {
  display: block;
}

@media (min-width: 1280px) and (max-width: 1440px) {
  .landing-hero {
    padding-block-end: 22rem;
  }

  .hero-animation-img {
    margin-block-start: -25rem;
  }
}

@media (min-width: 900px) and (max-width: 1279px) {
  .landing-hero {
    padding-block-end: 14rem;
  }

  .hero-animation-img {
    margin-block-start: -17rem;
  }
}

@media (min-width: 768px) and (max-width: 899px) {
  .landing-hero {
    padding-block-end: 12rem;
  }

  .hero-animation-img {
    margin-block-start: -15rem;
  }
}

@media (min-width: 600px) and (max-width: 767px) {
  .landing-hero {
    padding-block-end: 8rem;
  }

  .hero-animation-img {
    margin-block-start: -11rem;
  }
}

@media (min-width: 425px) and (max-width: 600px) {
  .landing-hero {
    padding-block-end: 8rem;
  }

  .hero-animation-img {
    margin-block-start: -9rem;
  }
}

@media (min-width: 300px) and (max-width: 424px) {
  .landing-hero {
    padding-block-end: 6rem;
  }

  .hero-animation-img {
    margin-block-start: -7rem;
  }
}

.landing-hero::before {
  position: absolute;
  background-repeat: no-repeat;
  inset-block: 0;
  opacity: 0.5;
}

.landing-hero-dark-bg {
  background-color: #25293c;
  background-image: url("@images/front-pages/backgrounds/hero-bg.png");
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}

.landing-hero-light-bg {
  background: url("@images/front-pages/backgrounds/hero-bg.png") center no-repeat, linear-gradient(138.18deg, #eae8fd 0%, #fce5e6 94.44%);
  background-size: cover;
}

@media (min-width: 650px) {
  .hero-text-box {
    inline-size: 38rem;
    margin-block-end: 1rem;
    margin-inline: auto;
  }
}

@media (max-width: 599px) {
  .hero-title {
    font-size: 1.5rem !important;
    line-height: 2.375rem !important;
  }
}

.hero-title {
  animation: shine 2s ease-in-out infinite alternate;
  background: linear-gradient(135deg, #28c76f 0%, #5a4aff 47.92%, #ff3739 100%);
  //  stylelint-disable-next-line property-no-vendor-prefix
  -webkit-background-clip: text;
  background-clip: text;
  background-size: 200% auto;
  font-size: 42px;
  font-weight: 800;
  line-height: 48px;
  -webkit-text-fill-color: rgba(0, 0, 0, 0%);
}

@keyframes shine {
  0% {
    background-position: 0% 50%;
  }

  80% {
    background-position: 50% 90%;
  }

  100% {
    background-position: 91% 100%;
  }
}

.hero-dashboard-img {
  margin-block: 0;
  margin-inline: auto;
  max-inline-size: 700px;
  transform-style: preserve-3d;
  transition: all 0.35s;

  img {
    inline-size: 100%;
  }
}

.feature-cards {
  margin-block-start: 6.25rem;
}

.hero-btn-item {
  inset-block-start: 80%;
  inset-inline-start: 5%;
}
</style>

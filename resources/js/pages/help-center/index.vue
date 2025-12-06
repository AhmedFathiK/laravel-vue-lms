<script setup>
import AppSearchHeader from '@/components/AppSearchHeader.vue'
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import HelpCenterLandingArticlesOverview from '@/views/help-center/HelpCenterLandingArticlesOverview.vue'
import HelpCenterLandingFooter from '@/views/help-center/HelpCenterLandingFooter.vue'
import HelpCenterLandingKnowledgeBase from '@/views/help-center/HelpCenterLandingKnowledgeBase.vue'
import { useConfigStore } from '@/@core/stores/config'
import { ref } from 'vue'

// fetching data from fake-api
const store = useConfigStore()

store.skin = 'default'
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const apiData = ref({
  popularArticles: [
    {
      slug: 'getting-started',
      title: 'Getting Started',
      img: '../images/svg/rocket.svg',
      subtitle: 'Whether you\'re new or you\'re a power user, this article will',
    },
    {
      slug: 'first-steps',
      title: 'First Steps',
      img: '../images/svg/gift.svg',
      subtitle: 'Are you a new customer wondering how to get started?',
    },
    {
      slug: 'external-content',
      title: 'Add External Content',
      img: '../images/svg/keyboard.svg',
      subtitle: 'Article will show you how to expand functionality of App',
    },
  ],
  allArticles: [
    {
      title: 'Buying',
      icon: 'tabler-shopping-cart',
      articles: [
        { title: 'What are Favourites?' },
        { title: 'How do I purchase an item?' },
        { title: 'How do i add or change my details?' },
        { title: 'How do refunds work?' },
        { title: 'Can I Get A Refund?' },
        { title: 'I\'m trying to find a specific item' },
      ],
    },
    {
      title: 'Item Support',
      icon: 'tabler-help',
      articles: [
        { title: 'What is Item Support?' },
        { title: 'How to contact an author?' },
        { title: 'Where Is My Purchase Code?' },
        { title: 'Extend or renew Item Support' },
        { title: 'Item Support FAQ' },
        { title: 'Why has my item been removed?' },
      ],
    },
    {
      title: 'Licenses',
      icon: 'tabler-currency-dollar',
      articles: [
        { title: 'Can I use the same license for the...' },
        { title: 'How to contact an author?' },
        { title: 'I\'m making a test site - it\'s not for ...' },
        { title: 'which license do I need?' },
        { title: 'I want to make multiple end prod ...' },
        { title: 'For logo what license do I need?' },
      ],
    },
    {
      title: 'Template Kits',
      icon: 'tabler-color-swatch',
      articles: [
        { title: 'Template Kits' },
        { title: 'Elementor Template Kits: PHP Zip ...' },
        { title: 'Template Kits - Imported template ...' },
        { title: 'Troubleshooting Import Problems' },
        { title: 'How to use the WordPress Plugin ...' },
        { title: 'How to use the Template Kit Import ...' },
      ],
    },
    {
      title: 'Account & Password',
      icon: 'tabler-lock-open',
      articles: [
        { title: 'Signing in with a social account' },
        { title: 'Locked Out of Account' },
        { title: 'I\'m not receiving the verification email' },
        { title: 'Forgotten Username Or Password' },
        { title: 'New password not accepted' },
        { title: 'What is Sign In Verification?' },
      ],
    },
    {
      title: 'Account Settings',
      icon: 'tabler-user',
      articles: [
        { title: 'How do I change my password?' },
        { title: 'How do I change my username?' },
        { title: 'How do I close my account?' },
        { title: 'How do I change my email address?' },
        { title: 'How can I regain access to my a ...' },
        { title: 'Are RSS feeds available on Market?' },
      ],
    },
  ],
  keepLearning: [
    {
      slug: 'blogging',
      title: 'Blogging',
      img: '../images/svg/laptop.svg',
      subtitle: 'Expert tips & tools to improve your website or online store using blog.',
    },
    {
      slug: 'inspiration-center',
      title: 'Inspiration Center',
      img: '../images/svg/lightbulb.svg',
      subtitle: 'inspiration from experts to help you start and grow your big ideas.',
    },
    {
      slug: 'community',
      title: 'Community',
      img: '../images/svg/discord.svg',
      subtitle: 'A group of people living in the same place or having a particular.',
    },
  ],
})

// // ℹ️ Check if MSW service worker is registered and ready to intercept requests
// setTimeout(async () => {
//   const faqData = await $api('/pages/help-center')
//
//   apiData.value = faqData
// }, 1000)
</script>

<template>
  <div class="help-center-page">
    <Navbar />
    <div v-if="apiData && apiData.allArticles.length">
      <AppSearchHeader
        subtitle="Common troubleshooting topics: eCommerce, Blogging to payment"
        custom-class="rounded-0"
        placeholder="Search"
      >
        <template #title>
          <h4
            class="text-h4 font-weight-medium"
            style="color: rgba(var(--v-theme-primary), 1);"
          >
            Hello, how can we help?
          </h4>
        </template>
      </AppSearchHeader>

      <!-- 👉 Popular Articles -->
      <div class="help-center-section bg-surface">
        <VContainer>
          <h4 class="text-h4 text-center mb-6">
            Popular Articles
          </h4>
          <HelpCenterLandingArticlesOverview :articles="apiData.popularArticles" />
        </VContainer>
      </div>

      <!-- 👉 Knowledge Base -->
      <div class="help-center-section">
        <VContainer>
          <h4 class="text-h4 text-center mb-6">
            Knowledge Base
          </h4>
          <HelpCenterLandingKnowledgeBase :categories="apiData.allArticles" />
        </VContainer>
      </div>

      <!-- 👉 Keep Learning -->
      <div class="help-center-section bg-surface">
        <VContainer>
          <h4 class="text-h4 text-center mb-6">
            Keep Learning
          </h4>
          <HelpCenterLandingArticlesOverview :articles="apiData.keepLearning" />
        </VContainer>
      </div>

      <!-- 👉 Still need help? -->
      <div class="help-center-section">
        <HelpCenterLandingFooter />
      </div>

      <div>
        <Footer />
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.help-center-page {
  .search-header {
    background-size: cover !important;
    padding-block: 9.25rem 4.75rem !important;
  }

  .help-center-section {
    padding-block: 5.25rem;
  }
}

@media (max-width: 960px) and (min-width: 600px) {
  .help-center-page {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}

@media (max-width: 599px) {
  .help-center-page {
    .search-header {
      padding-block: 7rem 2rem !important;
      padding-inline: 2rem !important;
    }

    .help-center-section {
      padding-block: 3.5rem;
    }
  }
}
</style>

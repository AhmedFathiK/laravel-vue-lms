import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { createApp } from 'vue'

//toastifications
import { useConfigStore } from '@core/stores/config'
import Toast from "vue-toastification"
import "vue-toastification/dist/index.css"

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'

// Create vue app
const app = createApp(App)


// Register plugins
registerPlugins(app)

// register toastifications after pinia has been registered by registerPlugins
const configStore = useConfigStore()

const toastOptions = {
  position: configStore.isAppRTL ? "top-left" : "top-right",
  rtl: configStore.isAppRTL ? true : false,
  timeout: 5000,
  closeOnClick: false,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: "button",
  icon: true,
  transition: "Vue-Toastification__fade",
  newestOnTop: true,
}

app.use(Toast, toastOptions)

// Mount vue app
app.mount('#app')

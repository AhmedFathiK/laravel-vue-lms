import { createMongoAbility } from '@casl/ability'
import { abilitiesPlugin } from '@casl/vue'

export default function (app) {
  const initialAbility = createMongoAbility([])

  app.use(abilitiesPlugin, initialAbility, {
    useGlobalProperties: true,
  })
}

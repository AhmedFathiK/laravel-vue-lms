import { useAuthStore } from '@/stores/auth'
import { createMongoAbility } from '@casl/ability'

const ability = createMongoAbility()

const getAbility = (forceRefresh = false) => {
  let userAbilities = useAuthStore().abilities  
  
  return ability.update(userAbilities)
}

export default getAbility

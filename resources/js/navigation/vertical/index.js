export default [
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
    action: 'view',
    subject: 'users',
  },
  { 
    heading: "Users",
    abilities: [
      { subject: 'users', action: ['view'] },
      { subject: 'admin_panel', action: ['access'] },
    ],
  },
  {
    title: 'Users',
    to: { name: 'admin-users' },
    icon: { icon: 'tabler-users' },
    action: 'view',
    subject: 'users',
  },
  {
    title: 'Roles & Permissions',
    to: { name: 'admin-roles' },
    icon: { icon: 'tabler-lock' },
    action: 'view',
    subject: 'users',
  },
  { 
    heading: "Education",
    abilities: [
      { subject: 'courses', action: ['view'] },
      { subject: 'course_categories', action: ['view'] },
    ],
  },
  {
    title: 'All Courses',
    to: 'admin-courses',
    subject: 'courses',
    action: 'view',
    icon: { icon: 'tabler-book' },
  },
  {
    title: 'Categories',
    to: 'admin-courses-categories',
    subject: 'course_category',
    action: 'view',
    icon: { icon: 'tabler-category' },
  },
  { 
    heading: "General",
    abilities: [
      { subject: 'trash', action: ['view'] },
    ],
  },
  {
    title: 'Trash',
    to: { name: 'admin-trash' },
    icon: { icon: 'tabler-trash' },
    action: 'view',
    subject: 'trash',
  },
  { 
    heading: "Gamification",
    abilities: [
      { subject: 'trophies', action: ['view'] },
    ],
  },
  {
    title: 'Trophies',
    to: { name: 'admin-trophies' },
    icon: { icon: 'tabler-trophy' },
    action: 'view',
    subject: 'trophies',
  },
  
]

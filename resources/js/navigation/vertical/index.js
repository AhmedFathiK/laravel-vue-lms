export default [
  { 
    heading: "Users",
    abilities: [
      { subject: 'Branches', action: ['view', 'delete'] },
      { subject: 'General', action: ['view_dashboard'] },
      { subject: 'users', action: ['view', 'edit1'] },
    ],
  },
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
    action: 'view',
    subject: 'users',
  },
  {
    title: 'Second page',
    to: { name: 'second-page' },
    icon: { icon: 'tabler-file' },
    action: 'view',
    subject: 'users',
  },
]

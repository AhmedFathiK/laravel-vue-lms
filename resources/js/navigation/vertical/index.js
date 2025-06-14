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
    title: 'Courses',
    icon: { icon: 'tabler-book' },
    children: [
      {
        title: 'All Courses',
        to: '/admin/courses',
      },
      {
        title: 'Categories',
        to: '/admin/courses/categories',
      },
      {
        title: 'Course Levels',
        to: '/admin/courses/levels',
      },
      {
        title: 'Subscription Plans',
        to: '/admin/courses/subscription-plans',
      },
    ],
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

import { PrismaClient } from '../generated/prisma-client';

const prisma = new PrismaClient();

async function main() {
  const categories = [
    { name: 'Dairy', slug: 'dairy' },
    { name: 'Bakery', slug: 'bakery' },
    { name: 'Groceries', slug: 'groceries' },
  ];

  for (const category of categories) {
    await prisma.category.upsert({
      where: { slug: category.slug },
      update: {},
      create: category,
    });
  }

  const existingUser = await prisma.user.findFirst({ where: { phone: '9999999999' } });
  if (!existingUser) {
    await prisma.user.create({
      data: {
        name: 'Demo Customer',
        phone: '9999999999',
        role: 'CUSTOMER',
      },
    });
  }

  const existingAdmin = await prisma.user.findFirst({ where: { phone: '9999999998' } });
  if (!existingAdmin) {
    await prisma.user.create({
      data: {
        name: 'Demo Admin',
        phone: '9999999998',
        role: 'ADMIN',
      },
    });
  }
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });

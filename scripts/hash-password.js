const bcrypt = require('bcrypt');

const password = process.argv[2];

if (!password) {
  console.error('Usage: npm run hash-password -- "votre-mot-de-passe"');
  process.exit(1);
}

bcrypt.hash(password, 12).then((hash) => {
  console.log('\nAjoutez cette ligne dans votre fichier .env :\n');
  console.log(`ADMIN_PASSWORD_HASH=${hash}\n`);
});

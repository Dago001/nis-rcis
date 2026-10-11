const fs = require('fs');
const path = require('path');
const sampleEnv = `APP_URL=https://rcis.nimcoselection.com
API_URL=https://rcis.nimcoselection.com/api
SESSION_SECRET="mysecret12345678901234567890123456"
`;
fs.writeFileSync('c:/nis-rcis-new/scratch/sample.env', sampleEnv);

const envFile = fs.readFileSync('c:/nis-rcis-new/scratch/sample.env', 'utf8');
envFile.split('\n').forEach(line => {
  const match = line.match(/^\s*([\w.-]+)\s*=\s*(.*)?\s*$/);
  if (match) {
    const key = match[1];
    let value = (match[2] || '').trim();
    if (value.startsWith('"') && value.endsWith('"')) value = value.slice(1, -1);
    if (!process.env[key]) process.env[key] = value;
  }
});

console.log('PARSED APP_URL:', process.env.APP_URL);
console.log('PARSED SESSION_SECRET:', process.env.SESSION_SECRET);
/**
 * cPanel MySQL defaults for auraai-vps.com (xghchgcjhfy account).
 * Override via DB_* env vars in Bun/Node (server.ts, API routes).
 */
export const CPANEL_DB = {
  host: 'localhost',
  user: 'auraaiadmin',
  password: 'auraai@2026',
  database: 'auraai',
  port: 3306,
} as const;

export function resolveDbConfig() {
  return {
    host:
      process.env.DB_HOST ||
      process.env.MYSQLHOST ||
      process.env.MYSQL_HOST ||
      CPANEL_DB.host,
    user:
      process.env.DB_USER ||
      process.env.MYSQLUSER ||
      process.env.MYSQL_USER ||
      CPANEL_DB.user,
    password:
      process.env.DB_PASSWORD ||
      process.env.MYSQLPASSWORD ||
      process.env.MYSQL_PASSWORD ||
      CPANEL_DB.password,
    database:
      process.env.DB_NAME ||
      process.env.MYSQLDATABASE ||
      process.env.MYSQL_DATABASE ||
      CPANEL_DB.database,
    port: Number(
      process.env.DB_PORT ||
        process.env.MYSQLPORT ||
        process.env.MYSQL_PORT ||
        CPANEL_DB.port
    ),
  };
}

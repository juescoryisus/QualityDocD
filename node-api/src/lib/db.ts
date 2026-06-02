import { drizzle } from "drizzle-orm/node-postgres";
import { Pool } from "pg";
import { companiesTable } from "../../schema/companies";
import { usersTable } from "../../schema/users";
import { documentsTable, documentVersionsTable } from "../../schema/documents";
import { searchIndexTable } from "../../schema/search-index";

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
});

export const db = drizzle(pool);

export { companiesTable, usersTable, documentsTable, documentVersionsTable, searchIndexTable };
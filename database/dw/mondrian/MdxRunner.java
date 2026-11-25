import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;

import org.olap4j.Cell;
import org.olap4j.CellSet;
import org.olap4j.CellSetAxis;
import org.olap4j.OlapConnection;
import org.olap4j.Position;
import org.olap4j.metadata.Member;

/**
 * Menjalankan semua query MDX di olap_mdx_queries.mdx terhadap skema Mondrian FORMA
 * dan mencetak hasilnya. Blok yang punya baris "-- @sql" dibandingkan dengan query SQL
 * langsung ke umkm_dw (sel tunggal): hasilnya PASS jika selisih < 0,01.
 *
 * Pemakaian (lihat run_mdx.ps1):
 *   java -cp <lib Mondrian>;<mysql-connector>;. MdxRunner <schema.xml> <queries.mdx> [host] [db] [user] [password]
 */
public class MdxRunner {

    record Block(String name, String op, String mdx, String sql) {}

    public static void main(String[] args) throws Exception {
        Path schema = Path.of(args[0]).toAbsolutePath();
        Path queries = Path.of(args[1]);
        String host = args.length > 2 ? args[2] : "127.0.0.1";
        String db = args.length > 3 ? args[3] : "umkm_dw";
        String user = args.length > 4 ? args[4] : "root";
        String pass = args.length > 5 ? args[5] : "";

        String jdbc = "jdbc:mysql://" + host + ":3306/" + db
                + "?useSSL=false&allowPublicKeyRetrieval=true&serverTimezone=Asia/Jakarta";

        Class.forName("com.mysql.cj.jdbc.Driver");
        Class.forName("mondrian.olap4j.MondrianOlap4jDriver");

        String olapUrl = "jdbc:mondrian:Jdbc=" + jdbc
                + ";JdbcUser=" + user + ";JdbcPassword=" + pass
                + ";JdbcDrivers=com.mysql.cj.jdbc.Driver"
                + ";Catalog=" + schema.toUri();

        int pass_ = 0, fail = 0;
        try (Connection raw = DriverManager.getConnection(olapUrl);
             Connection sqlConn = DriverManager.getConnection(jdbc, user, pass)) {
            OlapConnection olap = raw.unwrap(OlapConnection.class);

            for (Block b : parse(Files.readString(queries, StandardCharsets.UTF_8))) {
                System.out.println("==================================================================");
                System.out.println(b.name() + (b.op().isEmpty() ? "" : "   [" + b.op() + "]"));
                System.out.println("------------------------------------------------------------------");
                CellSet cs = olap.createStatement().executeOlapQuery(b.mdx());
                print(cs);

                if (!b.sql().isEmpty()) {
                    double mdxVal = ((Number) cs.getCell(0).getValue()).doubleValue();
                    double sqlVal;
                    try (Statement st = sqlConn.createStatement(); ResultSet rs = st.executeQuery(b.sql())) {
                        rs.next();
                        sqlVal = rs.getDouble(1);
                    }
                    boolean ok = Math.abs(mdxVal - sqlVal) < 0.01;
                    if (ok) pass_++; else fail++;
                    System.out.printf("MDX = %,.2f | SQL = %,.2f | %s%n", mdxVal, sqlVal, ok ? "PASS" : "FAIL");
                }
            }
        }
        System.out.println("==================================================================");
        System.out.printf("Perbandingan MDX vs SQL: %d PASS, %d FAIL%n", pass_, fail);
        if (fail > 0) System.exit(1);
    }

    static List<Block> parse(String text) {
        List<Block> out = new ArrayList<>();
        String name = null, op = "", sql = "";
        StringBuilder mdx = new StringBuilder();
        for (String line : text.split("\\R")) {
            String t = line.trim();
            if (t.startsWith("-- @name")) {
                if (name != null) out.add(new Block(name, op, mdx.toString().trim(), sql));
                name = t.substring(8).trim(); op = ""; sql = ""; mdx.setLength(0);
            } else if (t.startsWith("-- @op")) {
                op = t.substring(6).trim();
            } else if (t.startsWith("-- @sql")) {
                sql = t.substring(7).trim();
            } else if (!t.startsWith("--") && name != null) {
                mdx.append(line).append('\n');
            }
        }
        if (name != null) out.add(new Block(name, op, mdx.toString().trim(), sql));
        return out;
    }

    static void print(CellSet cs) {
        List<CellSetAxis> axes = cs.getAxes();
        if (axes.isEmpty()) {
            System.out.println(cs.getCell(0).getFormattedValue());
            return;
        }
        CellSetAxis cols = axes.get(0);
        StringBuilder header = new StringBuilder(String.format("%-38s", ""));
        for (Position p : cols.getPositions()) header.append(String.format("%22s", caption(p)));
        System.out.println(header);
        if (axes.size() == 1) {
            StringBuilder row = new StringBuilder(String.format("%-38s", ""));
            for (Position c : cols.getPositions()) row.append(String.format("%22s", fmt(cs.getCell(c))));
            System.out.println(row);
            return;
        }
        for (Position r : axes.get(1).getPositions()) {
            StringBuilder row = new StringBuilder(String.format("%-38s", truncate(caption(r), 37)));
            for (Position c : cols.getPositions()) row.append(String.format("%22s", fmt(cs.getCell(c, r))));
            System.out.println(row);
        }
    }

    static String caption(Position p) {
        StringBuilder sb = new StringBuilder();
        for (Member m : p.getMembers()) {
            if (sb.length() > 0) sb.append(" | ");
            sb.append(m.getCaption());
        }
        return sb.toString();
    }

    static String fmt(Cell c) {
        String v = c.getFormattedValue();
        return v == null || v.isEmpty() ? "-" : v;
    }

    static String truncate(String s, int n) {
        return s.length() <= n ? s : s.substring(0, n - 1) + "~";
    }
}

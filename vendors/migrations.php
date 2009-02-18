<?php
/**
 * Classe mae que as migraçoes irao herdar
 */
class Migration {
    /**
     * Funçao de criaçao de tabela
     */
    function createTable(){}
    /**
     * Funcao de alteraçao de tabela
     */
    function changeTable(){}
    /**
     * Funçao de excluir tabela
     */
    function dropTable(){}
    /**
     * Adicionar colunas
     */
    function addColumn(){}
    /**
     * Remover colunas
     */
    function removeColumn(){}
    /**
     * Alterar colunas
     */
    function changeColumn(){}
    /**
     * Renomear colunas
     */
    function renameColumn(){}
    /**
     * Adicionar Index
     */
    function addIndex(){}
    /**
     * Remover Index
     */
    function removeIndex(){}
}
?>